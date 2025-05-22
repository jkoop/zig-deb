FROM debian:latest
ARG VERSION
RUN apt update && \
	apt install -y ca-certificates && \
	echo 'deb [trusted=true] https://packages.joekoop.com/ /' > /etc/apt/sources.list.d/joekoop.list && \
	apt update && \
	apt install -y zig=$VERSION && \
	rm /etc/apt/sources.list.d/joekoop.list
