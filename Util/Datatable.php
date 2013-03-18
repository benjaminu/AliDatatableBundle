<?php

namespace Ali\DatatableBundle\Util;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use Ali\DatatableBundle\Util\Factory\Query\DoctrineBuilder;
use Ali\DatatableBundle\Util\Formatter\Renderer;
use Ali\DatatableBundle\Util\Factory\Prototype\PrototypeBuilder;
use Ali\DatatableBundle\Util\Factory\Query\QueryInterface;

/**
 * Datatable class.
 *
 * @category  Util
 * @package   AliDatatbleBundle
 * @author    Benjamin Ugbene <benjamin.ugbene@googlemail.com>
 * @copyright 2013 Ali Hichem and Benjamin Ugbene
 */
class Datatable
{
    /** @var ContainerInterface */
    protected $container;

    /** @var \Doctrine\ORM\EntityManager */
    protected $em;

    /** @var Request */
    protected $request;

    /** @var QueryInterface */
    protected $queryBuilder;

    /** @var boolean */
    protected $hasAction = true;

    /** @var boolean */
    protected $hasRendererAction = false;

    /** @var array */
    protected $fixedData = null;

    /** @var closure */
    protected $renderer = null;

    /** @var array */
    protected $renderers = null;

    /** @var Renderer */
    protected $rendererObj = null;

    /** @var boolean */
    protected $search = false;
    protected static $instances = array();
    protected static $currentInstance = null;

    /**
     * Individual column sort statuses.
     *
     * @var array
     */
    protected $columnSortStatus = array();

    /**
     * Individual column visibility statuses.
     *
     * @var array
     */
    protected $columnVisibilityStatus = array();

    /**
     * Default datatable sort order.
     *
     * @var array
     */
    protected $sortOrder = array();

    /**
     * CSS classes for specific columns.
     *
     * @var array
     */
    protected $columnClasses = array();

    /**
     * class constructor
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container       = $container;
        $this->em              = $this->container->get('doctrine.orm.entity_manager');
        $this->request         = $this->container->get('request');
        $this->queryBuilder    = new DoctrineBuilder($container);
        self::$currentInstance = $this;
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
     *     ->setJoin(
     *         'r.event',
     *         'e',
     *         Join::INNER_JOIN,
     *         'e.name like %test%'
     *     )
     *
     * @return Datatable
     */
    public function addJoin($joinField, $alias, $type = Join::INNER_JOIN, $cond = '')
    {
        $this->queryBuilder->addJoin($joinField, $alias, $type, $cond);

        return $this;
    }

    /**
     * execute
     *
     * @param int $hydrationMode
     *
     * @return Response
     */
    public function execute($hydrationMode = Query::HYDRATE_ARRAY)
    {
        $request       = $this->request;
        $iTotalRecords = $this->queryBuilder->getTotalRecords();
        $data          = $this->queryBuilder->getData($hydrationMode);

        if (! is_null($this->fixedData)) {
            $this->fixedData = array_reverse($this->fixedData);
            foreach ($this->fixedData as $item) {
                array_unshift($data, $item);
            }
        }

        if (! is_null($this->renderer)) {
            array_walk($data, $this->renderer);
        }

        if (! is_null($this->rendererObj)) {
            $this->rendererObj->applyTo($data);
        }

        $output = array(
            'sEcho'                => intval($request->get('sEcho')),
            'iTotalRecords'        => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalRecords,
            'aaData'               => $data
        );

        return new Response(json_encode($output));
    }

    /**
     * get datatable instance by id
     *  return current instance if null
     *
     * @param string $id
     *
     * @return Datatable .
     */
    public static function getInstance($id)
    {
        $instance = null;

        if (array_key_exists($id, self::$instances)) {
            $instance = self::$instances[$id];
        } else {
            $instance = self::$currentInstance;
        }

        if (is_null($instance)) {
            throw new \Exception('No instance found for datatable, you should set a datatable id in your
            action with "setDatatableId" using the id from your view ');
        }

        return $instance;
    }

    /**
     * get entity name
     *
     * @return string
     */
    public function getEntityName()
    {
        return $this->queryBuilder->getEntityName();
    }

    /**
     * get entity alias
     *
     * @return string
     */
    public function getEntityAlias()
    {
        return $this->queryBuilder->getEntityAlias();
    }

    /**
     * get fields
     *
     * @return array
     */
    public function getFields()
    {
        return $this->queryBuilder->getFields();
    }

    /**
     * get hasAction
     *
     * @return boolean
     */
    public function getHasAction()
    {
        return $this->hasAction;
    }

    /**
     * retrun true if the actions column is overridden by twig renderer
     *
     * @return boolean
     */
    public function getHasRendererAction()
    {
        return $this->hasRendererAction;
    }

    /**
     * get order field
     *
     * @return string
     */
    public function getOrderField()
    {
        return $this->queryBuilder->getOrderField();
    }

    /**
     * get order type
     *
     * @return string
     */
    public function getOrderType()
    {
        return $this->queryBuilder->getOrderType();
    }

    /**
     * create raw prototype
     *
     * @param string $type
     *
     * @return PrototypeBuilder
     */
    public function getPrototype($type)
    {
        return new PrototypeBuilder($this->container, $type);
    }

    /**
     * get query builder
     *
     * @return QueryInterface
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * get search
     *
     * @return boolean
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * Get individual column sort statuses.
     *
     * @return array
     */
    public function getColumnSortStatus()
    {
        return $this->columnSortStatus;
    }

    /**
     * Get individual column visibility statuses.
     *
     * @return array
     */
    public function getColumnVisibilityStatus()
    {
        return $this->columnVisibilityStatus;
    }

    /**
     * Returns default datatable sort order.
     *
     * @return array
     */
    public function getSortOrder()
    {
        return $this->sortOrder;
    }

    /**
     * Returns css classes for specific columns.
     *
     * @return array
     */
    public function getColumnClasses()
    {
        return $this->columnClasses;
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
        $this->queryBuilder->setEntity($entityName, $entityAlias);

        return $this;
    }

    /**
     * set fields
     *
     * @param array $fields
     *
     * @return Datatable
     */
    public function setFields(array $fields)
    {
        $this->queryBuilder->setFields($fields);

        return $this;
    }

    /**
     * set has action
     *
     * @param type $hasAction
     *
     * @return Datatable
     */
    public function setHasAction($hasAction)
    {
        $this->hasAction = $hasAction;

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
        $this->queryBuilder->setOrder($orderField, $orderType);

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
     * set query builder
     *
     * @param QueryInterface $queryBuilder
     */
    public function setQueryBuilder(QueryInterface $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Set a php closure as renderer
     *
     * @param \Closure $renderer
     *
     * @example:
     *
     *  $controller_instance = $this;
     *  $datatable = $this->get('datatable')
     *       ->setEntity("AliBaseBundle:Entity", "e")
     *       ->setFields($fields)
     *       ->setOrder("e.created", "desc")
     *       ->setRenderer(
     *               function(&$data) use ($controller_instance)
     *               {
     *                   foreach ($data as $key => $value)
     *                   {
     *                       if ($key == 1)
     *                       {
     *                           $data[$key] = $controller_instance
     *                               ->get('templating')
     *                               ->render('AliBaseBundle:Entity:_decorator.html.twig',
     *                                       array(
     *                                           'data' => $value
     *                                       )
     *                               );
     *                       }
     *                   }
     *               }
     *         )
     *       ->setHasAction(true);
     *
     * @return Datatable
     */
    public function setRenderer(\Closure $renderer)
    {
        $this->renderer = $renderer;

        return $this;
    }

    /**
     * Set renderers as twig views
     *
     * @param array $renderers
     *
     * @example: To override the actions column
     *
     *      ->setFields(
     *          array(
     *             "field label 1" => 'x.field1',
     *             "field label 2" => 'x.field2',
     *             "_identifier_"  => 'x.id'
     *          )
     *      )
     *      ->setRenderers(
     *          array(
     *             2 => array(
     *               'view' => 'AliDatatableBundle:Renderers:_actions.html.twig',
     *               'params' => array(
     *                  'view_route'    => 'matche_view',
     *                  'edit_route'    => 'matche_edit',
     *                  'delete_route'  => 'matche_delete',
     *                  'delete_form_prototype'   => $datatable->getPrototype('delete_form')
     *               ),
     *             ),
     *          )
     *       )
     *
     * @return Datatable
     */
    public function setRenderers(array $renderers)
    {
        $this->renderers = $renderers;
        if (! empty($this->renderers)) {
            $this->rendererObj = new Renderer($this->container, $this->renderers, $this->getFields());
        }

        $actionsIndex = array_search('_identifier_', array_keys($this->getFields()));
        if ($actionsIndex != false && isset($renderers[$actionsIndex])) {
            $this->hasRendererAction = true;
        }

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
        $this->queryBuilder->setWhere($where, $params);

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
        $this->queryBuilder->setSearch($search);

        return $this;
    }

    /**
     * set datatable identifier
     *
     * @param string $id
     *
     * @return Datatable
     */
    public function setDatatableId($id)
    {
        if (! array_key_exists($id, self::$instances)) {
            self::$instances[$id] = $this;
        } else {
            throw new \Exception('Identifer already exists');
        }

        return $this;
    }

    /**
     * Set individual column sort statuses.
     *
     * @param array $columnSortStatus Default sort statuses.
     *
     * @return DoctrineBuilder
     */
    public function setColumnSortStatus($columnSortStatus)
    {
        $sortable = array();
        foreach ($columnSortStatus as $key => $value) {
            $sortable[$key] = (bool) $value;
        }

        $this->columnSortStatus = $sortable;

        return $this;
    }

    /**
     * Set individual column visibility statuses.
     *
     * @param array $columnVisibilityStatus Default sort statuses.
     *
     * @return DoctrineBuilder
     */
    public function setColumnVisibilityStatus($columnVisibilityStatus)
    {
        $visible = array();
        foreach ($columnVisibilityStatus as $key => $value) {
            $visible[$key] = (bool) $value;
        }

        $this->columnVisibilityStatus = $visible;

        return $this;
    }

    /**
     * Set default datatable sort order.
     * Use array of key => value pairs representing the column and
     * sort type respectively.
     * e.g. $sortOrder = array(0 => 'desc', 2 => 'asc')
     *
     * @param array $sortOrder
     *
     * @return Datatable
     */
    public function setSortOrder(array $sortOrder)
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * Sets css classes for specific columns.
     *
     * @param array $columnClasses
     *
     * @return Datatable
     */
    public function setColumnClasses(array $columnClasses)
    {
        $this->columnClasses = $columnClasses;

        return $this;
    }
}