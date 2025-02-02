<?php namespace Winter\Storm\Database;

/**
 * Custom collection used by NestedTree trait.
 *
 * General access methods:
 *
 *   $collection->toNested(); // Converts collection to an eager loaded one.
 *
 */
class TreeCollection extends Collection
{

    /**
     * Converts a flat collection of nested set models to an set where
     * children is eager loaded
     * @param bool $removeOrphans Remove nodes that exist without their parents.
     * @return \Winter\Storm\Database\Collection
     */
    public function toNested($removeOrphans = true)
    {
        /*
         * Set new collection for "children" relations
         */
        $collection = $this->getDictionary();
        foreach ($collection as $key => $model) {
            $model->setRelation('children', new Collection);
        }

        /*
         * Assign all child nodes to their parents
         */
        $nestedKeys = [];
        foreach ($collection as $key => $model) {
            if (!$parentKey = $model->getParentId()) {
                continue;
            }

            if (array_key_exists($parentKey, $collection)) {
                $collection[$parentKey]->children[] = $model;
                $nestedKeys[] = $model->getKey();
            }
            elseif ($removeOrphans) {
                $nestedKeys[] = $model->getKey();
            }
        }

        /*
         * Remove processed nodes
         */
        foreach ($nestedKeys as $key) {
            unset($collection[$key]);
        }

        return new Collection($collection);
    }

    /**
     * Gets an array with values of a given column. Values are indented according to their depth.
     * @param  string $value  Array values
     * @param  string $key    Array keys
     * @param  string $indent Character to indent depth
     * @return array
     */
    public function listsNested($value, $key = null, $indent = '&nbsp;&nbsp;&nbsp;')
    {
        /*
         * Recursive helper function
         */
        $buildCollection = function ($items, $depth = 0) use (&$buildCollection, $value, $key, $indent) {
            $result = [];

            $indentString = str_repeat($indent, $depth);

            foreach ($items as $item) {
                if ($key !== null) {
                    $result[$item->{$key}] = $indentString . $item->{$value};
                }
                else {
                    $result[] = $indentString . $item->{$value};
                }

                /*
                 * Add the children
                 */
                $childItems = $item->getChildren();
                if ($childItems->count() > 0) {
                    $result = $result + $buildCollection($childItems, $depth + 1);
                }
            }

            return $result;
        };

        /*
         * Build a nested collection
         */
        $rootItems = $this->toNested();
        return $buildCollection($rootItems);
    }

    /**
     * Gets an nested array with values of a given columns.
     * @param  mixed  $values Model columns to return, either a string name of the role or an array of names.
     * @param  string $key    Model column to use as key
     * @return array
     */
    public function toNestedArray($values, $key = null)
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        /*
         * Recursive helper function
         */
        $buildCollection = function ($items) use (&$buildCollection, $values, $key) {
            $result = [];

            foreach ($items as $item) {
                $itemArray = [];

                if ($key !== null) {
                    $itemArray[$item->{$key}] = $item->only($values);
                }
                else {
                    $itemArray = array_merge($itemArray, $item->only($values));
                }

                /*
                 * Add the children
                 */
                $childItems = $item->getChildren();
                if ($childItems->count() > 0) {
                    if ($key !== null) {
                        $itemArray[$item->{$key}]['children'] = $buildCollection($childItems);
                    } else {
                        $itemArray = array_merge($itemArray, ['children' => $buildCollection($childItems)]);
                    }
                }

                if ($key !== null) {
                    $result = $result + $itemArray;
                } else {
                    $result[] = $itemArray;
                }
            }

            return $result;
        };

        /*
         * Build a nested collection
         */
        $rootItems = $this->toNested();
        return $buildCollection($rootItems);
    }
}
