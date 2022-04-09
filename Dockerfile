# syntax=docker/dockerfile:1
FROM ubuntu:20.04
ARG DEBIAN_FRONTEND="noninteractive"

COPY scripts/docker_install_node.sh docker_install_node.sh
COPY scripts/docker_start.sh docker_start.sh
RUN chmod +x docker_start.sh
RUN bash docker_install_node.sh
CMD /docker_start.sh
