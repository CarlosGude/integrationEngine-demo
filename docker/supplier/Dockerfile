FROM nginx:alpine

COPY --chown=root:root prices.csv /usr/share/nginx/html/prices.csv
COPY --chown=root:root nginx.conf /etc/nginx/nginx.conf

RUN chmod 0444 /usr/share/nginx/html/prices.csv /etc/nginx/nginx.conf

USER nginx

EXPOSE 8080

ENTRYPOINT []
CMD ["nginx", "-g", "daemon off;"]
