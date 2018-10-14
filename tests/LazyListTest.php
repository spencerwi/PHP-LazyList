<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Spencerwi\LazyList;

final class LazyListTest extends TestCase {

    public function test_canBeCreatedFromAnArray() 
    {
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray([1,2,3]);

        $this->assertEquals(
            [1,2,3],
            $lazyList->toArray()
        );
    }

    public function test_canBeCreatedFromAGenerator() 
    {
        /** @var LazyList $lazyList */
        $lazyList = new LazyList(function ($i) {
            if ($i <= 5) {
                return $i * $i;
            } else {
                return null;
            }
        });

        $this->assertEquals(
            [0, 1, 4, 9, 16, 25],
            $lazyList->toArray()
        );
    }

    public function test_map_FromArray()
    {
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray([1,2,3]);

        $lazySquares = $lazyList->map(function($x) { 
            return $x * $x; 
        });

        $this->assertEquals(
            [1, 4, 9],
            $lazySquares->toArray()
        );
    }

    public function test_map_FromGenerator()
    {
        /** @var LazyList $lazyList */
        $lazyList = new LazyList(function($i) {
            if ($i < 4) {
                return $i;
            }
        });

        $lazySquares = $lazyList->map(function($x) { 
            return $x * $x; 
        });

        $this->assertEquals(
            [0, 1, 4, 9],
            $lazySquares->toArray()
        );
    }

    public function test_map_IsLazy() 
    {
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray([1,2,3]);

        $mapperFunctionWasInvoked = false;
        $lazyList->map(function($i) use (&$mapperFunctionWasInvoked) {
            $mapperFunctionWasInvoked = true;
        });

        $this->assertFalse($mapperFunctionWasInvoked);
    }

    public function test_reduce_FromArray() 
    {
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray([2,3,4]);

        /** @var int $result */
        $result = $lazyList->reduce(1, function($previous, $current) {
            return $previous + $current;
        });

        $this->assertEquals(1+2+3+4, $result);
    }

    public function test_reduce_FromEmptyArray() 
    {
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray([]);

        /** @var int $result */
        $result = $lazyList->reduce(1, function($previous, $current) {
            return $previous + $current;
        });

        $this->assertEquals(1, $result);
    }

    public function test_reduce_FromGenerator() 
    {
        /** @var LazyList $lazyList */
        $lazyList = new LazyList(function ($i) {
            if ($i < 4) {
                return $i + 1;
            } else {
                return null;
            }
        });

        /** @var int $result */
        $result = $lazyList->reduce(1, function($previous, $current) {
            return $previous + $current;
        });

        $this->assertEquals(1+1+2+3+4, $result);
    }

    public function test_reduce_FromEmptyGenerator() 
    {
        /** @var LazyList $lazyList */
        $lazyList = new LazyList(function ($i) {
            return null;
        });

        /** @var int $result */
        $result = $lazyList->reduce(1, function($previous, $current) {
            return $previous + $current;
        });

        $this->assertEquals(1, $result);
    }

    public function test_CanBeIterated_FromArray() 
    {
        $originalArray = [1,2,3];
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray($originalArray);

        $i = 0;
        foreach ($lazyList as $index => $element) {
            $this->assertEquals(
                $i, 
                $index,
                "The iteration key should be the current index!"
            );
            $this->assertEquals(
                $originalArray[$index],
                $element,
                "The list should be iterated correctly!"
            );
            $i++;
        }
    }

    public function test_CanBeIterated_FromGenerator() 
    {
        /** @var LazyList $lazyList */
        $lazyList = new LazyList(function ($i) {
            if ($i < 4) {
                return $i + 1;
            } else {
                return null;
            }
        });

        $i = 0;
        foreach ($lazyList as $index => $element) {
            $this->assertEquals(
                $i, 
                $index,
                "The iteration key should be the current index!"
            );
            $this->assertEquals(
                $i + 1,
                $element,
                "The list should be iterated correctly!"
            );
            $i++;
        }
    }

    /**
     * @dataProvider data_test_take_FromArray
     */
    public function test_take_FromArray(array $inputArray, int $count, array $expectedResult) 
    {
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray($inputArray);

        $this->assertEquals(
            $expectedResult,
            $lazyList->take($count)
        );
    }
    /**
     * DataProvider for @see test_take_FromArray
     * @return array test cases in the form [inputArray, count, expectedResult]
     */
    public static function data_test_take_FromArray() : array 
    {
        return [
            "Take some but not all" => [[1,2,3,4,5], 3, [1,2,3]],
            "Take all" => [[1,2,3,4,5], 5, [1,2,3,4,5]],
            "Take none" => [[1,2,3,4,5], 0, []],
            "Take more than all" => [[1,2,3,4,5], 99, [1,2,3,4,5]],
        ];
    }

    /**
     * @dataProvider data_test_take_FromGenerator
     */
    public function test_take_FromGenerator(callable $generator, int $count, array $expectedResult) 
    {
        /** @var LazyList $lazyList */
        $lazyList = new LazyList($generator);

        $this->assertEquals(
            $expectedResult,
            $lazyList->take($count)
        );
    }

    /**
     * DataProvider for @see test_take_FromGenerator
     * @return array test cases in the form [generator, count, expectedResult]
     */
    public static function data_test_take_FromGenerator() : array 
    {
        return [
            "Take some but not all" => [static::generatorUpTo(5), 3, [0,1,2]],
            "Take all" => [static::generatorUpTo(5), 6, [0,1,2,3,4,5]],
            "Take none" => [static::generatorUpTo(5), 0, []],
            "Take more than all" => [static::generatorUpTo(5), 99, [0,1,2,3,4,5]],
        ];
    }

    /**
     * @dataProvider data_test_getAt_FromArray
     */
    public function test_getAt_FromArray(array $inputArray, int $at, $expectedResult) 
    {
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray($inputArray);

        $this->assertEquals(
            $lazyList->getAt($at),
            $expectedResult
        );
    }

    /**
     * DataProvider for @see test_getAt_FromArray
     * @return array test cases in the form [inputArray, at, expectedResult]
     */
    public static function data_test_getAt_FromArray() : array 
    {
        return [
            "Start" => [[1,2,3,4], 0, 1],
            "Middle" => [[1,2,3,4], 2, 3],
            "End" => [[1,2,3,4], 3, 4],
            "Past end" => [[1,2,3,4], 99, null],
        ];
    }

    /**
     * @dataProvider data_test_getAt_FromGenerator
     */
    public function test_getAt_FromGenerator(callable $generator, int $at, $expectedResult) 
    {
        /** @var LazyList $lazyList */
        $lazyList = new LazyList($generator);

        $this->assertEquals(
            $lazyList->getAt($at),
            $expectedResult
        );
    }

    /**
     * DataProvider for @see test_getAt_FromGenerator
     * @return array test cases in the form [generator, at, expectedResult]
     */
    public static function data_test_getAt_FromGenerator() : array 
    {
        return [
            "Start" => [static::generatorUpTo(4), 0, 0],
            "Middle" => [static::generatorUpTo(4), 2, 2],
            "End" => [static::generatorUpTo(4), 4, 4],
            "Past end" => [static::generatorUpTo(4), 99, null],
        ];
    }

    /**
     * Convenience method for creating a generator that returns 0...$max
     * @return callable a generator function.
     */
    private static function generatorUpTo(int $max) : callable 
    {
        return function($i) use ($max) {
            if ($i <= $max)  {
                return $i;
            } else {
                return null;
            }
        };

    }
}
