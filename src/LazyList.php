<?php

namespace Spencerwi;


class LazyList implements \Iterator {
    /** @var callable $generatorFn */
    private $generatorFn;
    
    /** @var int $currentIndex */
    private $currentIndex;

    /**
     * Constructs a new LazyList from a "generator function" that takes the 
     * current index as an int and returns either a value or null, where null
     * means "end-of-list".
     * For example:
     *
     *   $lazyList = new LazyList(new function(int $i) {
     *     if ($i <= 5) {
     *       return $i * $i;
     *     } else {
     *       return null;
     *     }
     *   });
     *
     * resulting in a LazyList of [0, 1, 4, 9, 16, 25];
     *
     * @param callable $generatorFn 
     */
    public function __construct(callable $generatorFn) 
    {
        $this->generatorFn = $generatorFn;
        $this->currentIndex = 0;
    }

    /**
     * Creates a lazy wrapper around an existing array.
     *
     * @return LazyList a lazy wrapper around the array.
     */
    public static function fromArray(array $arr) : LazyList
    {
        return new LazyList(
            function ($i) use ($arr) {
                return $arr[$i] ?? null;
            }
        );
    }

    /** 
     * Returns a new LazyList that will, on evaluation, apply the given function
     * to every element.
     *
     * @param callable $f the function to apply to every element in the new LazyList.
     * @return LazyList a new LazyList with $f "lazily applied" to it.
     */
    public function map(callable $f) : LazyList
    {
		// I need a variable so I can hand this callable property into the 
		// closure.
		$generatorFn = $this->generatorFn;
        return new LazyList(
            function (int $i) use ($f, $generatorFn) {
				// Do an explicit null-check to ensure we don't over-iterate 
				// forever and so that callers don't have to manually null-check
				$next = $generatorFn($i);
				if ($next !== null) {
					return $f($next);       
				}
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
     *  $lazyList = new LazyList( 
     * 
     * @param mixed $initial the "initial" value to use
     * @param callable $reducerFn
     * @return mixed
     **/
    public function reduce($initial, callable $reducerFn)
    {
        $result = $initial;
		$i = 0;
        $next = $this->getAt($i);
        while ($next !== null) {
            $result = $reducerFn($result, $next);
            $i++;
            $next = $this->getAt($i);
        }
        return $result;
    }

	/**
	 * Dumps the entire list out to an array. BEWARE: infinite lists will 
	 * loop infinitely here!
	 */
    public function toArray() : array 
    {
        $arr = [];
        $i = 0;
        $next = $this->getAt($i);
        while ($next !== null) {
            $arr[] = $next;
            $i++;
            $next = $this->getAt($i);
        }
        return $arr;
    }

	/**
	 * Gets a subarray of this list, taking the first $count values.
	 * If $count exceeds the length of this list, the entire list is returned,
	 * without "padding" nulls.
	 *
	 * @param int $count how many elements to take.
	 * @return array the first $count values in this list.
	 */
    public function take(int $count) : array 
    {
        if ($count > 0) {
            $i = 0;
            $arr = [];
            $next = $this->getAt($i);
            while ($next !== null && $i < $count) {
                $arr[] = $next;
                $i++;
                $next = $this->getAt($i);
            }
			return $arr;
        } else {
            return [];
        }
    }

	/**
	 * Gets a value for a specific index from this LazyList.
	 * @param int $index
	 * @return mixed the value at that index, or null
	 */
	public function getAt(int $index) 
	{
		// PHP is weird about letting you call member properties that are
		// callables.
		$generatorFn = $this->generatorFn; 
		return $generatorFn($index);
	}

	/**
	 * Returns the value at the current spot in the list.
	 *
	 * @return mixed
	 */
	public function current() 
	{
		return $this->getAt($this->currentIndex);
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
    }

	/** 
	 * Determines if the current location is "valid" for iteration,
	 * i.e. if the current iteration index has a value. This requires
	 * invoking the generator function, so be aware of that.
	 */
	public function valid() : bool
	{
		return ($this->current() !== null);
	}
}
