#!/bin/sh

cd $(dirname $0)

redocly bundle openapi.yaml -o openapi.bundle.yml
