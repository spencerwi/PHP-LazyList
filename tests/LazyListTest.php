<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Spencerwi\Lazy_list\LazyList;

final class LazyListTest extends TestCase {

    public function test_canBeCreatedFromAnArray(): void 
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = LazyList::fromArray([1,2,3]);

        $this->assertEquals(
            [1,2,3],
            $lazyList->toArray()
        );
    }

    public function test_canBeCreatedFromAGenerator(): void 
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = new LazyList(function (int $i) {
            if ($i <= 5) {
                return $i * $i;
            } else {
                return LazyList::STOP_ITERATION;
            }
        });

        $this->assertEquals(
            [0, 1, 4, 9, 16, 25],
            $lazyList->toArray()
        );
    }

    public function test_map_FromArray(): void
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = LazyList::fromArray([1,2,3]);

        $lazySquares = $lazyList->map(function(int $x) { 
            return $x * $x; 
        });

        $this->assertEquals(
            [1, 4, 9],
            $lazySquares->toArray()
        );
    }

    public function test_map_FromGenerator(): void
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = new LazyList(function(int $i) {
            if ($i < 4) {
                return $i;
			}
			return LazyList::STOP_ITERATION;
        });

        $lazySquares = $lazyList->map(function(int $x) { 
            return $x * $x; 
        });

        $this->assertEquals(
            [0, 1, 4, 9],
            $lazySquares->toArray()
        );
    }

    public function test_map_IsLazy(): void
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = LazyList::fromArray([1,2,3]);

        $mapperFunctionWasInvoked = false;
        $lazyList->map(function(int $i) use (&$mapperFunctionWasInvoked) {
            $mapperFunctionWasInvoked = true;
        });

        $this->assertFalse($mapperFunctionWasInvoked);
    }

    public function test_reduce_FromArray(): void
    {
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray([2,3,4]);

        /** @var int $result */
        $result = $lazyList->reduce(1, fn(int $previous, int $current): int => $previous + $current);

        $this->assertEquals(1+2+3+4, $result);
    }

    public function test_reduce_FromEmptyArray(): void
    {
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray([]);

        /** @var int $result */
        $result = $lazyList->reduce(1, fn(int $previous, int $current): int => $previous + $current);

        $this->assertEquals(1, $result);
    }

    public function test_reduce_FromGenerator(): void 
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = new LazyList(function(int $i) {
            if ($i < 4) {
                return $i + 1;
            } else {
                return LazyList::STOP_ITERATION;
            }
        });

        /** @var int $result */
        $result = $lazyList->reduce(1, fn(int $previous, int $current): int => $previous + $current);

        $this->assertEquals(1+1+2+3+4, $result);
    }

    public function test_reduce_FromEmptyGenerator(): void
    {
        /** @psalm-var LazyList<string> $lazyList */
        $lazyList = new LazyList(fn(int $i): string => LazyList::STOP_ITERATION);

        /** @var string $result */
        $result = $lazyList->reduce('', fn(string $previous, string $current): string => $previous . $current);

        $this->assertEquals('', $result);
    }

	public function test_filter_FromArray(): void
	{
        /** @psalm-var LazyList<string> $lazyList */
        $lazyList = LazyList::fromArray(['keep', 'drop', 'keep', 'keep', 'drop']);

		$keeps = $lazyList->filter(function(string $element) {
			return ($element === 'keep');
		});

		$this->assertEquals(
			['keep', 'keep', 'keep'],
			$keeps->toArray()
		);
	}

	public function test_filter_FromGenerator(): void
	{
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = new LazyList(function(int $i){
            if ($i < 4) {
                return $i + 1;
            }
            return LazyList::STOP_ITERATION;
        });

		$oddLazies = $lazyList->filter(fn(int $element): bool => ($element % 2 === 1));

		$this->assertEquals(
			[1, 3],
			$oddLazies->toArray()
		);
	}

    public function test_filter_IsLazy(): void
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = new LazyList(function(int $i){
            if ($i < 4) {
                return $i + 1;
            }
            return LazyList::STOP_ITERATION;
        });

        $filterFnWasCalled = false;

		$oddLazies = $lazyList->filter(function(int $element) use (&$filterFnWasCalled) {
            $filterFnWasCalled = true;
			return ($element % 2 === 1);
		});

		$this->assertFalse($filterFnWasCalled, 'The filter predicate should not be called until the LazyList is eager-evaluated!');
    }

    public function test_filter_then_map_FromArray(): void
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = LazyList::fromArray([0,1,2,3,4,5,6,7,8,9]);

		$oddLaziesSquared = $lazyList->filter(fn(int $element): bool => ($element % 2) === 1)
		    ->map(fn(int $element): int => $element * $element);

        $this->assertEquals(
            [1, 9, 25, 49, 81],
            $oddLaziesSquared->toArray()
        );
    }

    public function test_filter_then_map_FromGenerator(): void
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = new LazyList(function(int $i){
            if ($i < 10) {
                return $i + 1;
            }
            return LazyList::STOP_ITERATION;
        });

		$oddLaziesSquared = $lazyList->filter(fn(int $element): bool => ($element % 2) === 1)
		    ->map(fn(int $element): int => $element * $element);

        $this->assertEquals(
            [1, 9, 25, 49, 81],
            $oddLaziesSquared->toArray()
        );
    }


    public function test_map_then_filter_FromArray(): void
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = LazyList::fromArray([0,1,2,3,4,5,6,7,8,9]);

		$oddLaziesSquared = $lazyList->map(fn(int $element): int => $element * $element)
            ->filter(fn(int $element): bool => ($element % 2) === 1);

        $this->assertEquals(
            [1, 9, 25, 49, 81],
            $oddLaziesSquared->toArray()
        );
    }

    public function test_map_then_filter_FromGenerator(): void
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = new LazyList(function(int $i){
            if ($i < 10) {
                return $i + 1;
            }
            return LazyList::STOP_ITERATION;
        });

		$oddLaziesSquared = $lazyList->map(fn(int $element): int => $element * $element)
            ->filter(fn(int $element): bool => ($element % 2) === 1);

        $this->assertEquals(
            [1, 9, 25, 49, 81],
            $oddLaziesSquared->toArray()
        );
    }

    public function test_CanBeIterated_FromArray(): void
    {
        $originalArray = [1,2,3];
        /** @psalm-var LazyList<int> $lazyList */
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

    public function test_CanBeIterated_FromGenerator(): void
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = new LazyList(function (int $i) {
            if ($i < 4) {
                return $i + 1;
            } 
			return LazyList::STOP_ITERATION;
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

    public function test_filter_then_iterate_FromArray(): void
    {
        $originalArray = [1,2,3,4,5,6,7,8,9];
        $expectedResultOrder = [1,3,5,7,9];
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = LazyList::fromArray($originalArray);

        $oddLazies = $lazyList->filter(fn(int $element): bool => ($element % 2) === 1);

        $i = 0;
        $seen = [];
        foreach ($oddLazies as $index => $element) {
            $this->assertEquals(
                $i, 
                $index,
                "The iteration key should be the current index!"
            );
            $seen[] = $element;
            $i++;
        }
        $this->assertEquals($expectedResultOrder, $seen, 'Iteration order did not match expected');
    }

    public function test_filter_then_iterate_FromGenerator(): void
    {
        /** @psalm-var LazyList<int> $lazyList */
        $lazyList = new LazyList(function (int $i) {
            if ($i < 10) {
                return $i + 1;
            } 
			return LazyList::STOP_ITERATION;
        });
        $expectedResultOrder = [1,3,5,7,9];

        $oddLazies = $lazyList->filter(fn(int $element): bool => ($element % 2) === 1);

        $i = 0;
        $seen = [];
        foreach ($oddLazies as $index => $element) {
            $this->assertEquals(
                $i, 
                $index,
                "The iteration key should be the current index!"
            );
            $seen[] = $element;
            $i++;
        }
        $this->assertEquals($expectedResultOrder, $seen, 'Iteration order did not match expected');
    }


    /**
     * @dataProvider data_test_take_FromArray
     */
    public function test_take_FromArray(array $inputArray, int $count, array $expectedResult): void
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
    public static function data_test_take_FromArray(): array 
    {
        return [
            "Take some but not all" => [[1,2,3,4,5], 3, [1,2,3]],
            "Take all" => [[1,2,3,4,5], 5, [1,2,3,4,5]],
            "Take none" => [[1,2,3,4,5], 0, []],
            "Take more than all" => [[1,2,3,4,5], 99, [1,2,3,4,5]],
        ];
    }

    /**
     * @dataProvider data_test_filter_then_take_FromArray
     */
    public function test_filter_then_take_FromArray(array $inputArray, callable $predicate, int $count, array $expectedResult): void
    {
        /** @var LazyList $lazyList */
        $lazyList = LazyList::fromArray($inputArray);

        $this->assertEquals(
            $expectedResult,
            $lazyList->filter($predicate)->take($count)
        );
    }
    /**
     * DataProvider for @see test_filter_then_take_FromArray
     * @return array test cases in the form [inputArray, predicate, count, expectedResult]
     */
    public static function data_test_filter_then_take_FromArray(): array 
    {
        $isOdd = fn(int $i): bool => ($i % 2) === 1;
        return [
            "Take some of resulting list but not all" => [[1,2,3,4,5,6,7,8,9], $isOdd, 3, [1,3,5]],
            "Take all of resulting list" => [[1,2,3,4,5,6,7,8,9], $isOdd, 5, [1,3,5,7,9]],
            "Take none" => [[1,2,3,4,5], $isOdd, 0, []],
            "Take more than all" => [[1,2,3,4,5], $isOdd, 99, [1,3,5]],
        ];
    }

    /**
     * @dataProvider data_test_take_FromGenerator
     */
    public function test_take_FromGenerator(callable $generator, int $count, array $expectedResult): void
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
    public static function data_test_take_FromGenerator(): array 
    {
        return [
            "Take some but not all" => [static::generatorUpTo(4), 3, [0,1,2]],
            "Take all" => [static::generatorUpTo(4), 5, [0,1,2,3,4]],
            "Take none" => [static::generatorUpTo(4), 0, []],
            "Take more than all" => [static::generatorUpTo(4), 99, [0,1,2,3,4]],
        ];
    }


    /**
     * @dataProvider data_test_filter_then_take_FromGenerator
     */
    public function test_filter_then_take_FromGenerator(callable $generator, callable $predicate, int $count, array $expectedResult): void
    {
        /** @var LazyList $lazyList */
        $lazyList = new LazyList($generator);

        $this->assertEquals(
            $expectedResult,
            $lazyList->filter($predicate)->take($count)
        );
    }
    /**
     * DataProvider for @see test_filter_then_take_FromGenerator
     * @return array test cases in the form [generator, predicate, count, expectedResult]
     */
    public static function data_test_filter_then_take_FromGenerator(): array 
    {
        $isOdd = fn(int $i): bool => ($i % 2) === 1;
        return [
            "Take some of resulting list but not all" => [static::generatorUpTo(9), $isOdd, 3, [1,3,5]],
            "Take all of resulting list" => [static::generatorUpTo(9), $isOdd, 5, [1,3,5,7,9]],
            "Take none" => [static::generatorUpTo(5), $isOdd, 0, []],
            "Take more than all" => [static::generatorUpTo(5), $isOdd, 99, [1,3,5]],
        ];
    }

    /**
     * Convenience method for creating a generator that returns 0...$max
     * @return callable a generator function.
     */
    private static function generatorUpTo(int $max) : callable 
    {
        return function(int $i) use ($max) {
            if ($i <= $max)  {
                return $i;
            } else {
                return LazyList::STOP_ITERATION;
            }
        };

    }
}
