# MediaWiki code-utils

A collections of code utilities for MediaWiki.

# Lint and style checks

You can run PHP checks via composer:
```
composer install
composer test
```

Shell scripts should be passed through https://www.shellcheck.net/ . You can
use the Wikimedia Foundation CI container image:
```
docker run --rm -it -v "$(pwd):/src" docker-registry.wikimedia.org/releng/shellcheck
```
