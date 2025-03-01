#!/bin/sh

PRIVATE_KEY_FILE=my-auth-private.pem
PUBLIC_KEY_FILE=my-auth-public.pem
KEY_BITS=2048

cd $(dirname $0)

openssl genrsa -out $PRIVATE_KEY_FILE $KEY_BITS
openssl rsa -in $PRIVATE_KEY_FILE -pubout -out $PUBLIC_KEY_FILE
