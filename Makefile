.PHONY: default all

default: actions-console/webhooks/ActionsOnGoogleFulfillment.yaml

all: actions-console/webhooks/ActionsOnGoogleFulfillment.yaml server/index.html

actions-console/webhooks/ActionsOnGoogleFulfillment.yaml: actions-console/webhooks/ActionsOnGoogleFulfillment.template.yaml server/config.php
	php -r "require 'server/config.php'; \
		\$$webhook_url = WEBHOOK_URL_PATH.'?'.http_build_query(['token' => WEBHOOK_TOKEN]); \
		echo str_replace('<WEBHOOK_URL>', \$$webhook_url, file_get_contents('php://stdin')); \
		" < $< > $@

server/index.html: server/index-generator.php README.md
	php $< > $@
