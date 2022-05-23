docker-build:
	docker build --pull -t mtrdesign/s3-logs-parser .
	docker rm --force s3-logs-parser
	docker run --name s3-logs-parser \
		-v ${PWD}:/app \
		-it mtrdesign/s3-logs-parser \
		composer install

docker-bash:
	docker rm --force s3-logs-parser
	docker run --name s3-logs-parser \
		-v ${PWD}:/app \
		-it mtrdesign/s3-logs-parser \
		/bin/bash

run-phpcs:
	docker rm --force s3-logs-parser
	docker run --name s3-logs-parser \
		-v ${PWD}:/app \
		-it mtrdesign/s3-logs-parser \
		vendor/bin/phpcs -p --standard=PSR2 src tests

run-phpstan:
	docker rm --force s3-logs-parser
	docker run --name s3-logs-parser \
		-v ${PWD}:/app \
		-it mtrdesign/s3-logs-parser \
		vendor/bin/phpstan analyse --level 7 src

run-phpunit:
	docker rm --force s3-logs-parser
	docker run --name s3-logs-parser \
		-v ${PWD}:/app \
		-it mtrdesign/s3-logs-parser \
		vendor/bin/phpunit --coverage-clover=coverage.xml --verbose
