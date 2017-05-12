<?php

namespace Bolt\Storage\Migration\Processor;

use Bolt\Collection\Bag;
use Bolt\Collection\MutableBag;
use Bolt\Exception\InvalidRepositoryException;
use Bolt\Logger\LoggerContext;
use Bolt\Storage\Database\Schema\Manager as SchemaManager;
use Bolt\Storage\EntityManager;
use Bolt\Storage\Mapping\MetadataDriver;
use Bolt\Storage\Migration\Field;
use Bolt\Storage\Migration\Transformer\TypeTransformerInterface;
use Bolt\Storage\Repository;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\ConversionException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * Transformer for Entity data.
 *
 * @internal
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class TableRecordsProcessor
{
    /** @var EntityManager */
    private $entityManager;
    /** @var SchemaManager */
    private $schemaManager;
    /** @var MutableBag */
    private $typeTransformers;
    /** @var LoggerInterface */
    private $logger;
    /** @var int */
    private $maxResults;

    /**
     * Constructor.
     *
     * @param EntityManager        $em
     * @param SchemaManager        $manager
     * @param Bag                  $typeTransformers
     * @param LoggerInterface|null $logger
     * @param int                  $maxResults
     */
    public function __construct(EntityManager $em, SchemaManager $manager, Bag $typeTransformers, LoggerInterface $logger = null, $maxResults = 1000)
    {
        $this->entityManager = $em;
        $this->schemaManager = $manager;
        $this->typeTransformers = $typeTransformers;
        $this->maxResults = $maxResults;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Update all repositories entity field data and do any required
     * transformations.
     *
     * @param LoggerInterface $logger
     */
    public function transform()
    {
        $mapper = $this->entityManager->getMapper();
        $tables = $this->schemaManager->getInstalledTables();

        /** @var Table $table */
        foreach ($tables as $table) {
            $tableName = $table->getName();
            $tableMeta = MutableBag::from(['records' => null, 'fields' => null]);
            $context = LoggerContext::create()
                ->setSubject($tableName)
                ->setAction('processing_table')
                ->setMeta($tableMeta)
            ;
            try {
                $repo = $this->entityManager->getRepository($tableName);
                $this->log(LogLevel::INFO, 'Processing table: ' . $tableName, $context);
            } catch (InvalidRepositoryException $e) {
                // It isn't one of our tables, don't touch it.
                $this->log(LogLevel::INFO, 'Skipping table: ' . $tableName, null);
                continue;
            }
            $this->updateTable($mapper, $repo, $tableMeta);
        }
    }

    /**
     * @param MetadataDriver $mapper
     * @param Repository     $repo
     * @param MutableBag     $tableMeta
     */
    private function updateTable(MetadataDriver $mapper, Repository $repo, MutableBag $tableMeta)
    {
        $tableName = $repo->getTableName();
        $current = 0;
        $tableCount = $repo->count();
        if ($tableCount === 0) {
            // No records/rows in the table
            return;
        }

        $className = $mapper->resolveClassName($tableName);
        /** @var array $metaData */
        $metaData = $mapper->getClassMetadata($className) ?: $mapper->getClassMetadata($tableName);
        $fieldsMeta = Bag::fromRecursive($metaData);
        $qb = $this->entityManager->getConnection()
            ->createQueryBuilder()
            ->select('*')
            ->from($tableName)
        ;
        /** @var Index $identifier */
        $identifier = $metaData['identifier'];
        $IndexColumnNames = $identifier->getColumns();
        $IndexColumnName = reset($IndexColumnNames);

        while (true) {
            $qb->setFirstResult($current)
                ->setMaxResults($this->maxResults)
            ;
            $rows = $this->entityManager
                ->getConnection()
                ->fetchAll($qb)
            ;
            if (empty($rows)) {
                return;
            }
            $count = 0;

            foreach ($rows as $key => $row) {
                $primaryKeyValue = $row[$IndexColumnName];
                $context = LoggerContext::create()
                    ->setParent($tableName)
                    ->setSubject($primaryKeyValue)
                    ->setAction('processing_row')
                ;
                $this->log(LogLevel::DEBUG, 'Processing record ID: ' . $primaryKeyValue, $context);

                $this->updateColumnValues(MutableBag::from($row), $fieldsMeta['fields'], $repo, $tableMeta);
                unset($rows[$key]);

                ++$count;
            }
            $current = $current + $this->maxResults;
            $tableMeta->set('records', $count);
        }
    }

    /**
     * @param Bag        $row
     * @param Bag        $fieldsMeta
     * @param Repository $repo
     */
    private function updateColumnValues(Bag $row, Bag $fieldsMeta, Repository $repo, MutableBag $tableMeta)
    {
        $changed = 0;
        $qb = $repo->createQueryBuilder();
        $qb->update($repo->getTableName())
            ->where('id = :id')
            ->setParameter('id', $row->getPath('id'))
        ;

        foreach ($fieldsMeta as $fieldMeta) {
            $field = new Field($row, $fieldMeta);
            /** @var TypeTransformerInterface $transformer */
            foreach ($this->typeTransformers as $transformer) {
                $transformer->transform($field);
            }
            if ($field->hasChanged()) {
                $context = LoggerContext::create()
                    ->setParent($repo->getTableName())
                    ->setSubject($field->getName())
                    ->setAction('update_field')
                ;
                $this->log(LogLevel::DEBUG, 'Updated field "' . $field->getName() . '"', $context);
                $qb->set($field->getName(), ':field');
                $qb->setParameter('field', $field->getValue());
                ++$changed;
            }
        }
        if ($changed) {
            $qb->execute();
            $tableMeta->set('fields', $changed + $tableMeta->get('fields'));
        }

        try {
            $repo->find($row->getPath('id'));
        } catch (ConversionException $e) {
            $context = LoggerContext::create()
                ->setParent($repo->getTableName())
                ->setSubject($row->getPath('id'))
                ->setAction('update_row')
            ;

            $message = sprintf('%s record #%s still has a ConversionException: %s', $repo->getTableName(), $row->getPath('id'), $e->getMessage());
            $this->log(LogLevel::ERROR, $message, $context);
        }
    }

    private function log($level, $message, LoggerContext $context)
    {
        $this->logger->log($level, $message, $context->get());
    }
}
