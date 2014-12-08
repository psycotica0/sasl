#!/usr/bin/env bash

jabber_username=$1
jabber_password=$2

# Install ejabberd when control binary doesn't exists
if [ -z `which ejabberdctl` ]; then
    echo -n "Install ejabberd... "
    apt-get install -y ejabberd
    dpkg --configure ejabberd
    echo "done"
fi

ejabberd_config="/etc/ejabberd/ejabberd.cfg"
ejabberd_configured="/etc/ejabberd/configured"
ejabberd_restart=0

if [ ! -f "$ejabberd_configured" ]; then
    echo -n "Configure ejabberd... "

    service ejabberd stop > /dev/null

    cp "$ejabberd_config" "$ejabberd_config.orig"

    port_config=$(grep '  {5222, ejabberd_c2s, \[' $ejabberd_config)
    if [ -n "$port_config" ]; then
        ejabberd_restart=1
        sed -i 's/{5222, ejabberd_c2s,/{15222, ejabberd_c2s,/' "$ejabberd_config"
    fi

    host_config=$(grep '%% {hosts, \["localhost"\]}.' $ejabberd_config)
    if [ -n "$host_config" ]; then
        ejabberd_restart=1
        sed -i "s/{hosts, \\[\"localhost\"\\]}./{hosts, [\"localhost\", \"$HOSTNAME\"]}./" "$ejabberd_config"
    fi

    iptables -A INPUT -p tcp -m tcp --dport 22 -j ACCEPT
    iptables -A INPUT -p tcp -m tcp --dport 5222 -j ACCEPT
    iptables -A INPUT -p udp -m udp --dport 5222 -j ACCEPT
    iptables -A INPUT -p tcp -m tcp --dport 15222 -j ACCEPT
    iptables -A INPUT -p udp -m udp --dport 15222 -j ACCEPT
    iptables -A INPUT -p tcp -m tcp --dport 5223 -j ACCEPT
    iptables -A INPUT -p udp -m udp --dport 5223 -j ACCEPT
    iptables -A INPUT -p tcp -m tcp --dport 5269 -j ACCEPT
    iptables -A INPUT -p udp -m udp --dport 5269 -j ACCEPT
    iptables -A INPUT -p tcp -m tcp --dport 5280 -j ACCEPT
    iptables -A INPUT -p udp -m udp --dport 5280 -j ACCEPT
    iptables -A INPUT -p tcp -m tcp --dport 4369 -j ACCEPT
    iptables -A INPUT -p udp -m udp --dport 4369 -j ACCEPT
    iptables -A INPUT -p tcp -m tcp --dport 53873 -j ACCEPT

    touch $ejabberd_configured
    echo "done"
fi

if [ $ejabberd_restart -eq 1 ]; then
    echo "Trigger restart of ejabberd"
    service ejabberd restart > /dev/null
fi

# Adding users must be done after restarting the server, since hostname must be present
user_exists=$(ejabberdctl registered-users $HOSTNAME | grep "$jabber_username" )
if [ -z "$user_exists" ]; then
    echo -n "Register user '$jabber_username' with password '$jabber_password' at ejabberd... "

    # restart and wait before adding users, otherwise adding fails
    service ejabberd restart > /dev/null
    sleep 5

    ejabberdctl register "$jabber_username" "$HOSTNAME" "$jabber_password" > /dev/null
    echo "done"
fi
