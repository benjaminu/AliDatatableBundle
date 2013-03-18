<?php

namespace Ali\DatatableBundle\Util\Factory\Query;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * DoctrineBuilder class.
 *
 * @category  Util
 * @package   AliDatatbleBundle
 * @author    Benjamin Ugbene <benjamin.ugbene@googlemail.com>
 * @copyright 2013 Ali Hichem and Benjamin Ugbene
 */
class DoctrineBuilder implements QueryInterface
{
    /** @var ContainerInterface */
    protected $container;

    /** @var \Doctrine\ORM\EntityManager */
    protected $em;

    /** @var \Symfony\Component\HttpFoundation\Request */
    protected $request;

    /** @var \Doctrine\ORM\QueryBuilder */
    protected $queryBuilder;

    protected $entityName;
    protected $entityAlias;
    protected $fields;
    protected $orderField = null;
    protected $orderType  = 'asc';
    protected $where      = null;
    protected $joins      = array();
    protected $has_action = true;
    protected $fixedData  = null;
    protected $renderer   = null;
    protected $search     = false;

    /**
     * Default datetime format.
     *
     * @var string
     */
    const DEFAULT_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * class constructor
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container    = $container;
        $this->em           = $this->container->get('doctrine.orm.entity_manager');
        $this->request      = $this->container->get('request');
        $this->queryBuilder = $this->em->createQueryBuilder();
    }

    /**
     * get the search dql
     *
     * @param QueryBuilder $queryBuilder
     *
     * @return string
     */
    protected function _addSearch(QueryBuilder $queryBuilder)
    {
        if ($this->search == true) {
            $request = $this->request;

            $searchFields = array_values($this->fields);
            foreach ($searchFields as $i => $searchField) {
                if ($request->get("sSearch_{$i}")) {
                    $queryBuilder->andWhere(" $searchField like '%".$request->get("sSearch_{$i}")."%' ");
                }
            }
        }
    }

    /**
     * Add join
     *
     * @param string $joinField
     * @param string $alias
     * @param string $type
     * @param string $cond
     *
     * @example:
     *      ->setJoin(
     *          'r.event',
     *          'e',
     *          Join::INNER_JOIN,
     *          'e.name like %test%'
     *      )
     *
     * @return Datatable
     */
    public function addJoin($joinField, $alias, $type = Join::INNER_JOIN, $cond = '')
    {
        if ($cond != '') {
            $cond = " with {$cond} ";
        }

        $joinMethod = $type == Join::INNER_JOIN ? "innerJoin" : "leftJoin";
        $this->queryBuilder->$joinMethod($joinField, $alias, null, $cond);

        return $this;
    }

    /**
     * get total records
     *
     * @return integer
     */
    public function getTotalRecords()
    {
        $qb = clone $this->queryBuilder;
        $this->_addSearch($qb);
        $qb->select(" count({$this->fields['_identifier_']}) ");

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery($hydrationMode)
    {
        $request    = $this->request;
        $dqlFields  = array_values($this->fields);
        $orderField = current(explode(' as ', $dqlFields[$request->get('iSortCol_0')]));
        $qb         = clone $this->queryBuilder;

        if (! is_null($orderField)) {
            $qb->orderBy($orderField, $request->get('sSortDir_0', 'asc'));
        }

        if ($hydrationMode == Query::HYDRATE_ARRAY) {
            $qb->select(implode(" , ", $this->fields));
        } else {
            $qb->select($this->entityAlias);
        }

        $this->_addSearch($qb);
        $query          = $qb->getQuery();
        $iDisplayLength = (int) $request->get('iDisplayLength');

        if ($iDisplayLength > 0) {
            $query->setMaxResults($iDisplayLength)->setFirstResult($request->get('iDisplayStart'));
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function getData($hydrationMode)
    {
        $data  = array();
        $items = $this->getQuery($hydrationMode)
            ->getResult($hydrationMode);

        if ($hydrationMode == Query::HYDRATE_ARRAY) {
            foreach ($items as $item) {
                $data[] = array_values($item);
            }
        } else {
            foreach ($items as $item) {
                $_data       = array();
                $_entityData = $this->toArray($item);

                foreach ($this->fields as $field) {
                    $foreignKey = '';
                    list($foreignKey, $field) = explode('.', $field);

                    $fieldData = empty($_entityData[$foreignKey][$field]) ? null : $_entityData[$foreignKey][$field];

                    if (is_null($fieldData)) {
                        $fieldData = empty($_entityData[$field]) ? null : $_entityData[$field];

                        if (is_null($fieldData)) {
                            $fieldData = $this->getValue($item, $field);

                            if (is_object($fieldData)) {
                                $fieldData = (string) $fieldData;
                            }
                        }
                    }

                    $_data[] = $fieldData;
                }

                $data[] = $_data;
            }
        }

        return $data;
    }

    /**
     * get entity name
     *
     * @return string
     */
    public function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * get entity alias
     *
     * @return string
     */
    public function getEntityAlias()
    {
        return $this->entityAlias;
    }

    /**
     * get fields
     *
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * get order field
     *
     * @return string
     */
    public function getOrderField()
    {
        return $this->orderField;
    }

    /**
     * get order type
     *
     * @return string
     */
    public function getOrderType()
    {
        return $this->orderType;
    }

    /**
     * get doctrine query builder
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getDoctrineQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * set entity
     *
     * @param type $entityName
     * @param type $entityAlias
     *
     * @return Datatable
     */
    public function setEntity($entityName, $entityAlias)
    {
        $this->entityName  = $entityName;
        $this->entityAlias = $entityAlias;
        $this->queryBuilder->from($entityName, $entityAlias);

        return $this;
    }

    /**
     * set fields
     *
     * @param array $fields
     *
     * @return DoctrineBuilder
     */
    public function setFields(array $fields)
    {
        $this->fields = $fields;
        $this->queryBuilder->select(implode(', ', $fields));

        return $this;
    }

    /**
     * set order
     *
     * @param type $orderField
     * @param type $orderType
     *
     * @return Datatable
     */
    public function setOrder($orderField, $orderType)
    {
        $this->orderField = $orderField;
        $this->orderType  = $orderType;
        $this->queryBuilder->orderBy($orderField, $orderType);

        return $this;
    }

    /**
     * set fixed data
     *
     * @param type $data
     *
     * @return Datatable
     */
    public function setFixedData($data)
    {
        $this->fixedData = $data;

        return $this;
    }

    /**
     * set query where
     *
     * @param string $where
     * @param array  $params
     *
     * @return Datatable
     */
    public function setWhere($where, array $params = array())
    {
        $this->queryBuilder->where($where);
        $this->queryBuilder->setParameters($params);

        return $this;
    }

    /**
     * set search
     *
     * @param bool $search
     *
     * @return Datatable
     */
    public function setSearch($search)
    {
        $this->search = $search;

        return $this;
    }

    /**
     * set doctrine query builder
     *
     * @param QueryBuilder $queryBuilder
     *
     * @return DoctrineBuilder
     */
    public function setDoctrineQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    /**
     * Dynamic getter method.
     *
     * @param Entity $entity Entity.
     * @param string $field  Entity field.
     *
     * @return mixed
     */
    public function getValue($entity, $field)
    {
        $entityField = $field;

        $pos = strpos($field, '.');
        if ($pos) {
            $entityField = substr($field, $pos + 1);
        }

        $method = 'get'.ucfirst($entityField);
        if (method_exists($entity, $method)) {
            return $entity->$method();
        }

        return null;
    }

    /**
     * To array
     *
     * @param Entity $entity Entity.
     *
     * @return array
     */
    public function toArray($entity)
    {
        $metadata = $this->em->getClassMetadata(get_class($entity));

        $data = array();

        foreach ($metadata->fieldMappings as $field => $mapping) {
            // For some reason the load method is not triggered at this point
            // for Doctrine Proxies, so we have to manually trigger this method.
            if ($entity instanceof Proxy) {
                $entity->__load();
            }

            $value = $this->getValue($entity, $field);
            if ($value instanceof \DateTime) {
                // We cast DateTime to array to keep consistency with array result
                $data[$field] = (array) $value->format(
                    ! empty($mapping['options']['format']) ? $mapping['options']['format'] : self::DEFAULT_DATETIME_FORMAT
                );
            } elseif (is_object($value)) {
                $data[$field] = (string) $value;
            } else {
                $data[$field] = $value;
            }
        }

        foreach ($metadata->associationMappings as $field => $mapping) {
            if ($mapping['isCascadeDetach']) {
                $data[$key] = $metadata->reflFields[$field]->getValue($entity);
                if (null !== $data[$key]) {
                    $data[$key] = $this->_serializeEntity($data[$key]);
                }
            } elseif ($mapping['isOwningSide'] && $mapping['type'] & ClassMetadata::TO_ONE) {
                if (null !== $metadata->reflFields[$field]->getValue($entity)) {
                    $data[$mapping['fieldName']] = $this->toArray(
                        $this->em->getRepository($mapping['targetEntity'])->find(
                            $metadata->reflFields[$field]->getValue($entity)->getId()
                        )
                    );
                }
            }
        }

        return $data;
    }
}