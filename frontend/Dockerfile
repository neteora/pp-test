FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
ARG VITE_API_URL
ENV VITE_API_URL=$VITE_API_URL
RUN npm run build

FROM httpd:2.4
COPY --from=build /app/dist/ /usr/local/apache2/htdocs/
COPY spa.conf /usr/local/apache2/conf/extra/spa.conf
RUN echo "Include conf/extra/spa.conf" >> /usr/local/apache2/conf/httpd.conf
