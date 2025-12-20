FROM quay.io/centos/centos:stream10
RUN dnf install php-cli -y
RUN dnf install php-dom php-zip php-mbstring php-sysvmsg php-sqlite3 -y
WORKDIR /root/
COPY install-composer.sh /root/install-composer.sh
RUN chmod u+x /root/install-composer.sh
RUN /root/install-composer.sh
RUN mv /root/composer.phar /usr/local/bin/composer
RUN mkdir /home/jenkins -p
RUN chmod ug+rwx /home/jenkins
RUN chown :0 /home/jenkins
WORKDIR /home/jenkins
CMD ["sleep", "infinity"]
