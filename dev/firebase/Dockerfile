FROM node:22-alpine

WORKDIR /emulator

RUN apk add --update --no-cache\
	openjdk21-jre

RUN npm install -g firebase-tools

COPY \
	./.firebaserc\
	./firebase.json\
	./

EXPOSE 9099 4000

RUN mkdir ./data

VOLUME [ "/root/.cache" ]

CMD [ "npx", "firebase", "emulators:start", "--import=./data" ]
