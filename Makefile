.PHONY: install test cs cs-fix stan mutation deptrac qa ci help up down sh

help:
	@echo "Available targets:"
	@echo "  make install       - composer install"
	@echo "  make test          - phpunit"
	@echo "  make cs            - php-cs-fixer (dry-run)"
	@echo "  make cs-fix        - php-cs-fixer (fix)"
	@echo "  make stan          - phpstan analyse"
	@echo "  make mutation      - infection (mutation testing)"
	@echo "  make deptrac       - deptrac analyse"
	@echo "  make qa            - cs + stan + test + deptrac"
	@echo "  make ci            - qa + mutation"
	@echo "  make up            - docker compose up"
	@echo "  make down          - docker compose down"
	@echo "  make sh            - enter php container"

install:
	composer install

test:
	./vendor/bin/phpunit

cs:
	./vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	./vendor/bin/php-cs-fixer fix

stan:
	./vendor/bin/phpstan analyse --memory-limit=1G

mutation:
	./vendor/bin/infection --threads=max --show-mutations

deptrac:
	./vendor/bin/deptrac analyse --no-progress

qa: cs stan test deptrac

ci: qa mutation

up:
	docker compose up -d --wait

down:
	docker compose down

sh:
	docker compose exec -it php sh
