FROM nginx:1.27-alpine

COPY src/ /usr/share/nginx/html/
COPY assets/ /usr/share/nginx/html/assets/
COPY nginx-default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s \
  CMD wget --quiet --tries=1 --spider http://127.0.0.1/ || exit 1

