<?php

namespace Bolt\Logger;

use Bolt\Collection\Bag;

/**
 * PSR logger context class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
final class LoggerContext
{
    /** @var string */
    private $parent;
    /** @var string */
    private $subject;
    /** @var string */
    private $action;
    /** @var Bag|null */
    private $meta;

    private function __construct()
    {
    }

    public static function create()
    {
        return new self();
    }

    /**
     * @return array
     */
    public function get()
    {
        return [
            'parent'  => $this->parent,
            'subject' => $this->subject,
            'action'  => $this->action,
            'meta'    => $this->meta,
        ];
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param string $parent
     *
     * @return LoggerContext
     */
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     *
     * @return LoggerContext
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $action
     *
     * @return LoggerContext
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getMeta()
    {
        return $this->meta ? $this->meta->toArray() : null;
    }

    /**
     * @param Bag $meta
     *
     * @return LoggerContext
     */
    public function setMeta(Bag $meta = null)
    {
        $this->meta = $meta;

        return $this;
    }
}
