<?php

namespace Spencerwi\Lazy_list;

class FunctionUtils {

    public static function compose(callable $f, callable $g) : callable 
    {
        return function() use ($f, $g) {
            $arguments = func_get_args();
            return $f(call_user_func_array($g, $arguments));
        };
    }
}
