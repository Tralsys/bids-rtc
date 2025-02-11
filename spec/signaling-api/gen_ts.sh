#!/bin/sh

TARGET_DIR=$(dirname $0)/../../dev/signaling-api-ts

cd $(dirname $0)

openapi-generator generate \
    -i openapi.bundle.yml \
    -c config/ts-fetch.config.yaml \
    -g typescript-fetch \
    -o $TARGET_DIR \
