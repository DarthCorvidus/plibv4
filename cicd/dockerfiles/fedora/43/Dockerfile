FROM fedora:43
ENV HOME=/home/jenkins
ENV HOME=/home/jenkins
RUN dnf install -y php-cli php-common php-mbstring php-zip php-dom php-sysvmsg php-sqlite3 composer
RUN php -v

WORKDIR /root/
RUN mkdir /home/jenkins -p
RUN chmod ug+rwx /home/jenkins
RUN chown :0 /home/jenkins
WORKDIR /home/jenkins
CMD ["sleep", "infinity"]