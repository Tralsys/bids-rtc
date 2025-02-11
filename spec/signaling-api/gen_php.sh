#!/bin/sh

TARGET_DIR=$(dirname $0)/../../dev/signaling-api

cd $(dirname $0)

openapi-generator generate \
    -i openapi.bundle.yml \
    -c config/php-slim4.config.yaml \
    -g php-slim4 \
    -o $TARGET_DIR \
