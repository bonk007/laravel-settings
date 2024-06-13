<?php
/** @noinspection ALL */

namespace Settings\Contracts;

interface Configurable
{
    /**
     * Get primary key of the instance
     * @return mixed
     */
    public function getKey();

    /**
     * Get table name of the instance
     * @return string
     */
    public function getTable();
}
