Example apache httpd reverse proxy config:

```xml
<Proxy balancer://roundcube>
    BalancerMember http://kubernetes-node1:32100
    BalancerMember http://kubernetes-node2:32100
    BalancerMember http://kubernetes-node3:32100
    ProxySet lbmethod=byrequests
</Proxy>

RedirectPermanent "/roundcube" "https://example.com/roundcube/"
<LocationMatch "^/roundcube/(.*)$" >
    ProxyPass "balancer://roundcube/$1"
    ProxyPassReverse "balancer://roundcube/$1"
</LocationMatch>
```
