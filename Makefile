.PHONY: 

default: test

test: .PHONY
	./vendor/bin/phpunit --bootstrap vendor/autoload.php tests 
