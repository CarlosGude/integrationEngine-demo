FROM nginx:alpine

COPY --chown=nginx:nginx prices.csv /usr/share/nginx/html/prices.csv
COPY --chown=nginx:nginx nginx.conf /etc/nginx/nginx.conf

USER nginx

EXPOSE 8080

ENTRYPOINT []
CMD ["nginx", "-g", "daemon off;"]
