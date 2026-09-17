FROM nginx:alpine

COPY prices.csv /usr/share/nginx/html/prices.csv
COPY nginx.conf /etc/nginx/nginx.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
