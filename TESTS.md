# Testing

These tests utilize [Codeception](http://codeception.com/docs) and [WP Browser](https://github.com/lucatume/wp-browser).

## Installation

* `composer install`
* Create a new file `codeception.yml` with the env file you want to use:

```yaml
params:
	- .env.docker
```

## Running tests

```shell
./bin/codecept run integration
```
