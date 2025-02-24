#!/bin/sh

cd $(dirname $0)

openssl genrsa -out ./my-auth-private.pem 2048
openssl rsa -in ./my-auth-private.pem -pubout -out ./my-auth-private.pem
