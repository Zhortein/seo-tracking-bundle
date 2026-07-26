<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DataLifecycle\Grouping;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;
use Zhortein\SeoTrackingBundle\Tracking\Grouping\PageCallGroupingKeyGeneratorInterface;

final readonly class HistoricalGroupingKeyBackfiller
{
    private const GROUPING_FIELDS = ['url', 'campaign', 'medium', 'source', 'term', 'content', 'bot', 'groupingKey'];

    /**
     * @param class-string<PageCallInterface> $pageCallClass
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private PageCallGroupingKeyGeneratorInterface $groupingKeyGenerator,
        private DuplicatePageCallMergerInterface $duplicateMerger,
        private string $pageCallClass,
    ) {
    }

    public function run(GroupingBackfillOptions $options): GroupingBackfillResult
    {
        $entityManager = $this->managerRegistry->getManagerForClass($this->pageCallClass);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('No Doctrine ORM entity manager manages %s.', $this->pageCallClass));
        }

        /** @var ClassMetadata<PageCallInterface> $metadata */
        $metadata = $entityManager->getClassMetadata($this->pageCallClass);
        $this->validateMetadata($metadata);

        if ($options->mergeDuplicates && !$this->duplicateMerger->supports($this->pageCallClass)) {
            throw new \LogicException(sprintf('Duplicate consolidation for %s requires a custom %s service.', $this->pageCallClass, DuplicatePageCallMergerInterface::class));
        }

        return $options->apply
            ? $this->apply($entityManager, $metadata, $options)
            : $this->inspect($entityManager, $metadata, $options);
    }

    /**
     * @param ClassMetadata<PageCallInterface> $metadata
     */
    private function inspect(
        EntityManagerInterface $entityManager,
        ClassMetadata $metadata,
        GroupingBackfillOptions $options,
    ): GroupingBackfillResult {
        $scanned = 0;
        $backfillable = 0;
        $conflicts = 0;
        $invalid = 0;

        foreach ($this->historicalRows($entityManager, $metadata) as $pageCall) {
            ++$scanned;

            try {
                $group = $this->group($metadata, $pageCall);
            } catch (\UnexpectedValueException) {
                ++$invalid;

                continue;
            }

            $existing = $entityManager->getRepository($this->pageCallClass)
                ->findOneBy(['groupingKey' => $group['key']]);

            if ($existing instanceof PageCallInterface || $this->hasEarlierHistoricalPeer($entityManager, $metadata, $pageCall, $group)) {
                ++$conflicts;

                continue;
            }

            ++$backfillable;
        }

        return new GroupingBackfillResult(
            $scanned,
            $backfillable,
            $conflicts,
            $options->mergeDuplicates ? $conflicts : 0,
            $invalid,
            false,
        );
    }

    /**
     * @param ClassMetadata<PageCallInterface> $metadata
     */
    private function apply(
        EntityManagerInterface $entityManager,
        ClassMetadata $metadata,
        GroupingBackfillOptions $options,
    ): GroupingBackfillResult {
        $scanned = 0;
        $backfilled = 0;
        $conflicts = 0;
        $merged = 0;
        $invalid = 0;
        $processedSinceFlush = 0;

        /** @var array<string, PageCallInterface> $pending */
        $pending = [];

        foreach ($this->historicalRows($entityManager, $metadata) as $pageCall) {
            ++$scanned;
            ++$processedSinceFlush;

            try {
                $group = $this->group($metadata, $pageCall);
            } catch (\UnexpectedValueException) {
                ++$invalid;

                continue;
            }

            $existing = $pending[$group['key']] ?? $entityManager->getRepository($this->pageCallClass)
                ->findOneBy(['groupingKey' => $group['key']]);

            if ($existing instanceof PageCallInterface) {
                ++$conflicts;

                if ($options->mergeDuplicates) {
                    $this->duplicateMerger->merge($entityManager, $existing, $pageCall);
                    ++$merged;
                }
            } else {
                $metadata->setFieldValue($pageCall, 'groupingKey', $group['key']);
                $pending[$group['key']] = $pageCall;
                ++$backfilled;
            }

            if ($processedSinceFlush >= $options->batchSize) {
                $entityManager->flush();
                $entityManager->clear();
                $pending = [];
                $processedSinceFlush = 0;
            }
        }

        $entityManager->flush();
        $entityManager->clear();

        return new GroupingBackfillResult($scanned, $backfilled, $conflicts, $merged, $invalid, true);
    }

    /**
     * @param ClassMetadata<PageCallInterface> $metadata
     *
     * @return iterable<PageCallInterface>
     */
    private function historicalRows(EntityManagerInterface $entityManager, ClassMetadata $metadata): iterable
    {
        $identifier = $metadata->getSingleIdentifierFieldName();

        $query = $entityManager->createQueryBuilder()
            ->select('pageCall')
            ->from($this->pageCallClass, 'pageCall')
            ->where('pageCall.groupingKey IS NULL')
            ->orderBy(sprintf('pageCall.%s', $identifier), 'ASC')
            ->getQuery();

        foreach ($query->toIterable() as $pageCall) {
            if ($pageCall instanceof PageCallInterface) {
                yield $pageCall;
            }
        }
    }

    /**
     * @param ClassMetadata<PageCallInterface> $metadata
     * @param array{
     *     key: string,
     *     url: string,
     *     utm: array{campaign: ?string, medium: ?string, source: ?string, term: ?string, content: ?string},
     *     bot: bool
     * } $group
     */
    private function hasEarlierHistoricalPeer(
        EntityManagerInterface $entityManager,
        ClassMetadata $metadata,
        PageCallInterface $pageCall,
        array $group,
    ): bool {
        $identifier = $metadata->getSingleIdentifierFieldName();
        $identifierValue = $metadata->getIdentifierValues($pageCall)[$identifier] ?? null;
        if (!is_int($identifierValue) && !is_string($identifierValue)) {
            throw new \LogicException('Grouping-key backfill requires a scalar single-field entity identifier.');
        }

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select(sprintf('peer.%s', $identifier))
            ->from($this->pageCallClass, 'peer')
            ->where('peer.groupingKey IS NULL')
            ->andWhere(sprintf('peer.%s < :currentIdentifier', $identifier))
            ->andWhere($metadata->hasField('canonicalUrl')
                ? 'COALESCE(peer.canonicalUrl, peer.url) = :groupingUrl'
                : 'peer.url = :groupingUrl')
            ->andWhere('peer.bot = :bot')
            ->setParameter('currentIdentifier', $identifierValue)
            ->setParameter('groupingUrl', $group['url'])
            ->setParameter('bot', $group['bot'])
            ->setMaxResults(1);

        foreach ($group['utm'] as $field => $value) {
            if (null === $value) {
                $queryBuilder->andWhere(sprintf('peer.%s IS NULL', $field));
            } else {
                $queryBuilder
                    ->andWhere(sprintf('peer.%s = :%s', $field, $field))
                    ->setParameter($field, $value);
            }
        }

        return null !== $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * @param ClassMetadata<PageCallInterface> $metadata
     *
     * @return array{
     *     key: string,
     *     url: string,
     *     utm: array{campaign: ?string, medium: ?string, source: ?string, term: ?string, content: ?string},
     *     bot: bool
     * }
     */
    private function group(ClassMetadata $metadata, PageCallInterface $pageCall): array
    {
        $url = $metadata->hasField('canonicalUrl')
            ? $metadata->getFieldValue($pageCall, 'canonicalUrl')
            : null;
        $url ??= $metadata->getFieldValue($pageCall, 'url');
        if (!is_string($url) || '' === trim($url)) {
            throw new \UnexpectedValueException('Historical page call has no usable grouping URL.');
        }

        $utm = [
            'campaign' => $this->nullableStringField($metadata, $pageCall, 'campaign'),
            'medium' => $this->nullableStringField($metadata, $pageCall, 'medium'),
            'source' => $this->nullableStringField($metadata, $pageCall, 'source'),
            'term' => $this->nullableStringField($metadata, $pageCall, 'term'),
            'content' => $this->nullableStringField($metadata, $pageCall, 'content'),
        ];

        $bot = $metadata->getFieldValue($pageCall, 'bot');
        if (!is_bool($bot)) {
            throw new \UnexpectedValueException('Historical page call bot flag is not boolean.');
        }

        return [
            'key' => $this->groupingKeyGenerator->generate($url, $utm, $bot),
            'url' => $url,
            'utm' => $utm,
            'bot' => $bot,
        ];
    }

    /**
     * @param ClassMetadata<PageCallInterface> $metadata
     */
    private function nullableStringField(
        ClassMetadata $metadata,
        PageCallInterface $pageCall,
        string $field,
    ): ?string {
        $value = $metadata->getFieldValue($pageCall, $field);
        if (null !== $value && !is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Historical page call field "%s" is not a string.', $field));
        }

        return $value;
    }

    /**
     * @param ClassMetadata<PageCallInterface> $metadata
     */
    private function validateMetadata(ClassMetadata $metadata): void
    {
        foreach (self::GROUPING_FIELDS as $field) {
            if (!$metadata->hasField($field)) {
                throw new \LogicException(sprintf('Configured page call %s must map a "%s" field to use grouping-key backfill.', $this->pageCallClass, $field));
            }
        }

        if (1 !== count($metadata->getIdentifierFieldNames())) {
            throw new \LogicException('Grouping-key backfill requires a single-field entity identifier.');
        }
    }
}
