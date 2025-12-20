FROM debian:12
ENV HOME=/home/jenkins
RUN apt-get update
RUN apt-get install php8.2-cli git composer -y
RUN apt-get install php8.2-dom php8.2-sqlite3 -y
RUN mkdir /home/jenkins
RUN chmod ug+rwx /home/jenkins
RUN chown :0 /home/jenkins
WORKDIR /home/jenkins
CMD ["sleep", "infinity"]

