<?php

namespace Ongudidan\FortuneCore\Traits;

trait ConfiguresTableNames
{
    /**
     * Get the table name with the package prefix applied.
     */
    public function getTable()
    {
        $prefix = config('fortune.table_prefix', 'fc_');

        if (property_exists($this, 'baseTable')) {
            return $prefix.$this->baseTable;
        }

        $table = parent::getTable();

        // If the table already starts with the prefix, return it as is
        if (str_starts_with($table, $prefix)) {
            return $table;
        }

        return $prefix.$table;
    }
}
