FROM node:22-alpine

RUN apk add --no-cache python3 py3-pip gcc musl-dev python3-dev make g++

RUN pip3 install --break-system-packages \
    openpyxl \
    python-docx \
    pdfplumber \
    reportlab

WORKDIR /app

COPY . .

RUN npm install --legacy-peer-deps

RUN npm run build

EXPOSE 3000

CMD ["node", "dist/index.js"]
