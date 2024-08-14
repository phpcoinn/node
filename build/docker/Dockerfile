FROM ubuntu:22.04

ARG NETWORK=mainnet
ENV NETWORK=$NETWORK

ENV DEBIAN_FRONTEND=noninteractive

EXPOSE 80

RUN apt update && apt install sudo lsb-release -y \
&& apt install net-tools iputils-ping bc -y \
&& apt install wget nano -y

COPY docker_start.sh /

RUN chmod +x /docker_start.sh \
&& touch /first-run
CMD bash /docker_start.sh

