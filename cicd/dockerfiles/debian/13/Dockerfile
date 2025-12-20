FROM debian:13
ENV HOME=/home/jenkins
RUN apt-get update
RUN apt-get install php8.4-cli git composer -y
RUN apt-get install php8.4-dom php8.4-sqlite3 -y
RUN mkdir /home/jenkins
RUN chmod ug+rwx /home/jenkins
RUN chown :0 /home/jenkins
WORKDIR /home/jenkins
CMD ["sleep", "infinity"]
