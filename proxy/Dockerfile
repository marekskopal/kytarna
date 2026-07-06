FROM nginx:1.30.3-alpine AS build

ARG ADMINER_USER
ARG ADMINER_PASSWORD

RUN apk add --no-cache openssl && \
	# https://tools.ietf.org/html/rfc7919
	# https://github.com/mozilla/ssl-config-generator/blob/master/docs/ffdhe2048.txt
	wget -q https://ssl-config.mozilla.org/ffdhe2048.txt -O /etc/ssl/dhparam.pem

RUN \
	# Fail closed: only emit a credential line when BOTH user and password are set.
	# An empty/blank build arg would otherwise produce a ":<hash>" entry that grants
	# /adminer access with empty credentials. An empty .htpasswd makes nginx deny all.
	if [ -n "$ADMINER_USER" ] && [ -n "$ADMINER_PASSWORD" ]; then \
		HASH=$(printf '%s' "$ADMINER_PASSWORD" | openssl passwd -apr1 -stdin) && \
		printf '%s:%s\n' "$ADMINER_USER" "$HASH" > /etc/nginx/conf.d/.htpasswd; \
	else \
		: > /etc/nginx/conf.d/.htpasswd; \
	fi

FROM nginx:1.30.3-alpine

COPY --from=build /etc/ssl/dhparam.pem /etc/ssl/dhparam.pem
COPY --from=build /etc/nginx/conf.d/.htpasswd /etc/nginx/conf.d/.htpasswd
COPY ./conf/http.conf.template /etc/nginx/conf-available/http.conf.template
COPY ./conf/ssl.conf.template /etc/nginx/conf-available/ssl.conf.template
COPY ./docker-entrypoint.d/10-select-template.sh /docker-entrypoint.d/10-select-template.sh

RUN chmod +x /docker-entrypoint.d/10-select-template.sh
