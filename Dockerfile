FROM nginx:1.27-alpine

LABEL maintainer="RSUD Kilisuci"
LABEL description="Aplikasi Rekap Kompetensi Layanan RS Online RSUD Kilisuci"

# Ganti konfigurasi default Nginx
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Salin aset aplikasi web
COPY index.html /usr/share/nginx/html/
COPY assets/ /usr/share/nginx/html/assets/
COPY data/ /usr/share/nginx/html/data/
COPY vendor/ /usr/share/nginx/html/vendor/
COPY template/ /usr/share/nginx/html/template/

# Port HTTP
EXPOSE 80

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1/health || exit 1

CMD ["nginx", "-g", "daemon off;"]
