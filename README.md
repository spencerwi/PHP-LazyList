# PHP-LazyList

A lazy-list (or generator, or fused-stream, or seq, or whatever) library for PHP.

Supports PHP >=7.4.

## Usage

### Creation 

Lazy lists can be created from arrays
```php
$lazyList = \Spencerwi\Lazy_list\LazyList::fromArray([1,2,3,4,5]);
```

They can also be created from "generators", which take an index and return 
 either some value or else `\Spencerwi\Lazy_list\LazyList::STOP_ITERATION` to 
 signal the end of the list.

```php
$lazyList2 = new \Spencerwi\Lazy_list\LazyList(function(int $i) {
    if ($i < 10) {
        return $i;
    } else {
        return \Spencerwi\Lazy_list\LazyList::STOP_ITERATION;
    }
});
// This amounts to a LazyList containing [0,1,2,3,4,5,6,7,8,9].
```

### `map`

You can map over a lazy list, and it's a "lazy" map, so it doesn't call
your mapper function until you actually force evaluation of the list.

```php
$mapperWasCalled = false;
$squares = $lazyList->map(function($i): int use (&$mapperWasCalled) {
    $mapperWasCalled = true;
    return $i * $i;
});
// $mapperWasCalled is still false!
```

### `filter`

You can apply a filter predicate to a lazy list, and it'll "lazily" apply the 
filter, meaning that your filter function doesn't get called until you actually
force evaluation of the list.

```php
$filterFnWasCalled = false;
$oddNumbers = $lazyList->filter(function (int $i): bool use (&$filterFnWasCalled) {
    $filterFnWasCalled = true;
    return ($i % 2 === 1);
});
// $filterFnWasCalled is still false!
```

### Iteration

You can also iterate over a lazy list more traditionally; it's an iterator.

```php
foreach ($squares as $square) {
    echo $square . "\n";
}
/* Output:
 * 1
 * 4
 * 9
 * 16
 * 25
 */
```

It even supports index => value iteration:

```php
foreach ($squares as $index => $square) {
    echo $index . ": " . $square . "\n";
}
/* Output:
 * 0: 1
 * 1: 4
 * 2: 9
 * 3: 16
 * 4: 25
 */
```

### `take($count)`

You can also take just a certain number of elements from the beginning:

```php
$l = \Spencerwi\Lazy_list\LazyList::fromArray([1,2,3,4,5]);
$l->take(2); // returns the array [1,2]

// What happens when we take too many?
$l->take(99); // we get the whole list as an array: [1,2,3,4,5]
```

### `toArray()`

You can more directly dump the whole list out to an array with `toArray()`:

```php
$l = \Spencerwi\Lazy_list\LazyList::fromArray([1,2,3,4,5]);
$->toArray(); // [1,2,3,4,5]

```

**BE VERY CAREFUL WITH THIS**, as you can wind up using a generator to create an
infinite list (if your generator never returns null), which *will* cause an 
infinite loop if you call toArray() on it. Unfortunately, there's no way to know
ahead of time that a generator is infinite, or else we'd have the list throw an 
exception on trying to toArray() an infinite LazyList.

### `reduce()`

Alongside `map`, you also have its friend `reduce`, which takes an initial value,
and a function that works "pairwise" along the list calling a function you 
provide, first on initial and the first element, then on the previous result
and the second element, then on _that_ result and the third element, and so on.

```php
$l = \Spencerwi\Lazy_list\LazyList::fromArray([2,3,4]);
$sum = $l->reduce(1, function($previous, $current) {
  return $previous + $current;
});
// $sum is now ((1+2)+3)+4, that is, 10. 
```

If you try to "reduce" an empty list, you just get the "initial" value back. 
That's why it's there.

```php
$l = \Spencerwi\Lazy_list\LazyList::fromArray([]);
$sum = $l->reduce(1, function($previous, $current) {
  return $previous + $current;
});
// $sum is now just 1, since the list was empty
```

**THIS OPERATION IS "EAGER"**, meaning that **it evaluates the whole list**. As with
`toArray()`, that means that **if you have an infinite LazyList, `reduce()` will 
cause an infinite loop.**

