<?php

declare(strict_types=1);

namespace Devly\DI\Helpers;

use function array_intersect_key;
use function call_user_func_array;
use function is_array;

class Utils
{
    /**
     * Invokes all callbacks and returns array of results
     *
     * @param callable[] $callbacks
     * @param mixed      $args,...
     */
    public static function invokeBatch(iterable $callbacks, mixed ...$args): void
    {
        foreach ($callbacks as $callback) {
            call_user_func_array($callback, $args);
        }
    }

    /**
     * Recursively merges two fields. It is useful, for example, for merging tree structures. It behaves as
     * the + operator for array, ie. it adds a key/value pair from the second array to the first one and retains
     * the value from the first array in the case of a key collision.
     *
     * @param  array<T1> $array1
     * @param  array<T2> $array2
     *
     * @return array<T1|T2>
     *
     * @template T1
     * @template T2
     */
    public static function arrMergeTree(array $array1, array $array2): array
    {
        $res = $array1 + $array2;
        foreach (array_intersect_key($array1, $array2) as $k => $v) {
            if (! is_array($v) || ! is_array($array2[$k])) {
                continue;
            }

            $res[$k] = self::arrMergeTree($v, $array2[$k]);
        }

        return $res;
    }
}
