# docker build -t phpcoin-miner .
# docker run -it --rm --name phpcoin-miner -e NETWORK=testnet -e NODE=https://miner2.phpcoin.net -e ADDRESS=PtD756PoeBfw6KgCLqjpD4sYdZsaFu536F -e CPU=50 -e THREADS=2 phpcoin-miner
FROM php:8.1-cli

RUN apt update && apt install git libgmp-dev -y
RUN docker-php-ext-install gmp
RUN docker-php-ext-configure pcntl --enable-pcntl \
  && docker-php-ext-install pcntl
RUN mkdir /phpcoin
RUN cd /phpcoin && git clone https://github.com/phpcoinn/node.git .

COPY start.sh /
RUN chmod +x /start.sh
CMD /start.sh
