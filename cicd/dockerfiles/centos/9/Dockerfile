FROM quay.io/centos/centos:stream9
ENV HOME=/home/jenkins
#RUN dnf install php-cli -y
#RUN dnf install php-dom php-zip php-mbstring -y
RUN dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
RUN dnf module reset php -y
RUN dnf module enable php:remi-8.2 -y
RUN dnf install -y php-cli php-common php-mbstring php-zip php-dom
RUN php -v

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

