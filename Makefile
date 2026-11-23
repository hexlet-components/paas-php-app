# build и start повторяют команды публикации на Render и работают без mise:
# студенту курса нужен только рантайм стека. Остальные задачи make проксирует
# в mise run, они живут в mise.toml.
build:
	composer install

# С DATABASE_URL сначала применяются миграции, без неё bin/migrate.php ничего не делает.
start: build
	php bin/migrate.php
	php -S "0.0.0.0:$${PORT:-3000}" app.php

.PHONY: build start

# Тонкий прокси: make <задача> запускает mise run <задача>. Задачи живут в mise.toml.
%: FORCE
	@mise run $@

# Без этого правила make пытается собрать сам Makefile через прокси.
Makefile: ;
FORCE: ;
