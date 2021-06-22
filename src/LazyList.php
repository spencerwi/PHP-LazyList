<?php

namespace Spencerwi\Lazy_list;

/**
 * A Lazy List implementation that can be created either from
 * an array ({@see LazyList::fromArray}) or from a "generator"
 * callable that accepts the current index and returns either a value
 * or else {@see LazyList::STOP_ITERATION} to indicate the end of the list.
 * 
 * @template T
 */
class LazyList implements \Iterator {
	public const STOP_ITERATION = "\Spencerwi\Lazy_list\LazyList::STOP_ITERATION_TOKEN";
    private const SKIP_ELEMENT = "\Spencerwi\Lazy_list\LazyList::SKIP_ELEMENT_TOKEN";

    /** 
     * @var callable $generatorFn 
     * @psalm-var callable(int):T|self::STOP_ITERATION|self::SKIP_ELEMENT
     */
    private $generatorFn;
    
    /** @var int $currentIndex */
    private $currentIndex;

    /** @var int $skipOffset used for iterating with a "skip offset" so that we iterate consistently without repeating even after a filter is applied */
    private $skipOffset = 0;

    /**
     * Constructs a new LazyList from a "generator function" that takes the 
     * current index as an int and returns either a value or {@see LazyList::STOP_ITERATION}.
	 *
     * For example:
     *
     *   $lazyList = new LazyList(new function(int $i) {
     *     if ($i <= 5) {
     *       return $i * $i;
     *     } else {
     *       return LazyList::STOP_ITERATION;
     *     }
     *   });
     *
     * resulting in a LazyList of [0, 1, 4, 9, 16, 25];
     *
     * @param callable $generatorFn 
     * @psalm-param callable(int):T|self::STOP_ITERATION|self::SKIP_ELEMENT $generatorFn
     */
    public function __construct(callable $generatorFn) 
    {
        $this->generatorFn = $generatorFn;
        $this->currentIndex = 0;
    }

    /**
     * Creates a lazy wrapper around an existing array.
     *
     * @template TArrayElement
     * @psalm-param array<TArrayElement> $arr
     * @psalm-return LazyList<TArrayElement> a lazy wrapper around the array.
     * @return LazyList a lazy wrapper around the array.
     */
    public static function fromArray(array $arr) : LazyList
    {
        return new LazyList(
            function (int $i) use ($arr) {
				if ($i >= count($arr)) {
					return static::STOP_ITERATION;
				}
                return $arr[$i];
            }
        );
    }

    /** 
     * Returns a new LazyList that will, on evaluation, apply the given function
     * to every element.
     *
     * @template R
     * @psalm-param callable(T):R|self::STOP_ITERATION|self::SKIP_ELEMENT $f the function to apply to every element in the new LazyList.
     * @param callable $f the function to apply to every element in the new LazyList.
     * @psalm-return LazyList<R> a new LazyList with $f "lazily applied" to it.
     * @return LazyList
     */
    public function map(callable $f) : LazyList
    {
		// I need a variable so I can hand this callable property into the 
		// closure.
		$generatorFn = $this->generatorFn;
        return new LazyList(
            function (int $i) use ($f, $generatorFn) {
				// Do an explicit check for the end-of-list to ensure we don't 
				// over-iterate forever and so that callers don't have to 
				// manually do so.
				$next = $generatorFn($i);
                if ($this->shouldSkip($next)) {
                    return static::SKIP_ELEMENT;
                }
				if (!$this->isStopElement($next)) {
					return $f($next);       
				}
				return static::STOP_ITERATION;
            }
        );
    }

    /**
     * Lazily-applies a {@see callable} filter function to this list, so that 
     *  when you map/iterate/toArray/reduce later, the filter function will be
     *  used to skip elements that don't match your given predicate. Your 
     *  predicate should accept an element of the type in this list, and return
     *  a truthy or falsy value.
     * @param callable $predicate 
     * @psalm-param callable(T):bool $predicate 
     * @psalm-return LazyList<T>
     * @return LazyList
     */
	public function filter(callable $predicate): LazyList
	{
		$generatorFn = $this->generatorFn;
		return new LazyList(
			function (int $i) use ($predicate, $generatorFn) {
                $next = $generatorFn($i);
                if ($this->isStopElement($next)) {
                    return static::STOP_ITERATION;
                }

                $predicateIsSatisfied = $predicate($next);
                if ($predicateIsSatisfied) {
                    return $next;
                }
                return static::SKIP_ELEMENT;
			}
		);
	}

    /**
     * Reduces a LazyList into a single value, immediately evaluating the list.
     * The $reducerFn should accept (previous, current), starting with $initial 
     * as the first "previous" value, and return a value that will either be 
     * the next "previous" or the final result (if there's nothing left in the
     * list). For example:
     *
     *  $lazyList = LazyList::fromArray([1,2,3,4]);
     *  $result = $lazyList->reduce(0, function($total, $current){
     *     return $total + $current;
     *  });
     *  // $result is now 0+1+2+3+4, which is 10
     * 
     * @template TResult
     * @param mixed $initial the "initial" value to use
     * @psalm-param TResult $initial the "initial" value to use
     * @param callable $reducerFn
     * @psalm-param callable(TResult,T):TResult $reducerFn
     * @return mixed
     * @psalm-return TResult
     **/
    public function reduce($initial, callable $reducerFn)
    {
        $result = $initial;
		$i = 0;
        $next = $this->getAtInternal($i);
        while (!$this->isStopElement($next)) {
            if (!$this->shouldSkip($next)) {
                /** @psalm-var T $next */
                $result = $reducerFn($result, $next);
            }
            $i++;
            $next = $this->getAtInternal($i);
        }
        return $result;
    }

	/**
	 * Dumps the entire list out to an array. BEWARE: infinite lists will 
	 * loop infinitely here!
     * @psalm-return array<T>
     * @return array
	 */
    public function toArray() : array 
    {
        $arr = [];
        $i = 0;
        $next = $this->getAtInternal($i);
        while (!$this->isStopElement($next)) {
            if (!$this->shouldSkip($next)) {
                /** @psalm-var T $next */
                $arr[] = $next;
            }
            $i++;
            $next = $this->getAtInternal($i);
        }
        return $arr;
    }

	/**
	 * Gets a subarray of this list, taking the first $count values.
	 * If $count exceeds the length of this list, the entire list is returned,
	 * without "padding" nulls.
	 *
	 * @param int $count how many elements to take.
	 * @psalm-return array<T> the first $count values in this list.
	 * @return array the first $count values in this list.
	 */
    public function take(int $count) : array 
    {
        if ($count > 0) {
            $i = 0;
            $countTaken = 0;
            $arr = [];
            $next = $this->getAtInternal($i);
            while (!$this->isStopElement($next) && $countTaken < $count) {
                if (!$this->shouldSkip($next)) {
                    /** @psalm-var T $next */
                    $arr[] = $next;
                    $countTaken++;
                }
                $i++;
                $next = $this->getAtInternal($i);
            }
			return $arr;
        } else {
            return [];
        }
    }

    /**
	 * Internal getter for a value for a specific index from this LazyList, that 
     *  can return "special" values like {@see self::STOP_ITERATION} or {@see self::SKIP_ELEMENT}
	 * @param int $index
     * @psalm-return T|self::STOP_ITERATION|self::SKIP_ELEMENT
	 * @return mixed the value at that index, or the token that's at that index
	 */
    private function getAtInternal(int $index)
    {
		// PHP is weird about letting you call member properties that are
		// callables.
		$generatorFn = $this->generatorFn; 
		return $generatorFn($index);
    }

	/**
	 * Returns the value at the current spot in the list.
	 *
     * @psalm-return T
	 * @return mixed
	 */
	public function current() 
	{
        do {
            $result = $this->getAtInternal($this->currentIndex + $this->skipOffset);
            if ($this->shouldSkip($result)) {
                $this->skipOffset += 1; // increment our "skip offset" so we remember that we're offset by one.
            }
        } while ($this->shouldSkip($result));
        /** @psalm-var T $result at this point, we've confirmed that it's not a SKIP_ELEMENT, and iterator magic should protect us from STOP_ITERATION */
		return $result;
	}

	/**
	 * Returns the current iteration index in this list.
	 *
	 * @return int the current iteration index.
	 */
	public function key() : int 
	{
		return $this->currentIndex;
	}

    /** 
     * Advances one spot in the list.
     */
    public function next() 
    {
        $this->currentIndex++;
    }

    /**
     * Rewinds the iterator "cursor" to the beginning of the list.
     */
    public function rewind() 
    {
        $this->currentIndex = 0;
        $this->skipOffset = 0;
    }

	/** 
	 * Determines if the current location is "valid" for iteration,
	 * i.e. if the current iteration index has a value. This requires
	 * invoking the generator function, so be aware of that.
	 */
	public function valid() : bool
	{
		return ($this->current() !== static::STOP_ITERATION);
	}


    /**
     * @psalm-param T|self::SKIP_ELEMENT|self::STOP_ITERATION $element 
     * @psalm-assert-if-false T|self::SKIP_ELEMENT $element
     * @psalm-assert-if-true self::STOP_ITERATION $element
     * @return bool 
     */
    private function isStopElement($element): bool
    {
        return ($element === static::STOP_ITERATION);
    }

    /**
     * @psalm-param T|self::SKIP_ELEMENT|self::STOP_ITERATION $element 
     * @psalm-assert-if-false T|self::STOP_ITERATION $element
     * @psalm-assert-if-true self::SKIP_ELEMENT $element
     * @return bool 
     */
    private function shouldSkip($element): bool
    {
        return ($element === static::SKIP_ELEMENT);
    }
}
