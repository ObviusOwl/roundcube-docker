# Kubernets deploment with helm chart

This guide is highly adapted to my infrastructure, but also has been simplified 
and does not fully reflect the actual deployment. See the infra docs project for that.

Create PV/PVC

`pv-roundcube.yml`:

```yaml
kind: PersistentVolume
apiVersion: v1
metadata:
  name: roundcube
  annotations:
    pv.beta.kubernetes.io/gid: "5000"
  labels:
    volume-is-for: "roundcube-data"
spec:
  storageClassName: manual
  persistentVolumeReclaimPolicy: Retain
  capacity:
    storage: 2Gi
  accessModes:
    - ReadWriteMany
    - ReadWriteOnce
  claimRef:
    namespace: roundcube
    name: roundcube-data
  cephfs:
    monitors:
      - ceph-1:6789
      - ceph-2:6789
      - ceph-3:6789
    user: roundcube
    secretRef:
      namespace: roundcube
      name: ceph-secret
    path: "/pvs/roundcube/roundcube"
```

```sh
mkdir -p /media/cephfs/pvs/roundcube/roundcube
chown 0:5000 /media/cephfs/pvs/roundcube/roundcube
chmod 777 /media/cephfs/pvs/roundcube/roundcube
kubectl create -f pv-roundcube.yml
```

`roundcube-pvc.yml`

```yaml
kind: PersistentVolumeClaim
apiVersion: v1
metadata:
  name: roundcube-data
spec:
  accessModes:
    - ReadWriteMany
    - ReadWriteOnce
  resources:
    requests:
      storage: 2Gi
  storageClassName: manual
  selector:
    matchLabels:
      volume-is-for: "roundcube-data"
```

```sh
kubectl create -n roundcube -f roundcube-pvc.yml
```

Create the values yaml file:

`roundcube-values.yml`:

```yaml
replicaCount: 1

image:
  repository: reg.lan.terhaak.de/jojo/roundcube
  tag: 1.3.6-20190305-1
  pullPolicy: Always

persistence:
  existingClaim: roundcube-data

service:
  type: NodePort
  port: 80

securityContext:
  supplementalGroups: [5000] 

cleandbSchedule: "5 1 * * *"

ingress:
  enabled: false

resources: {}
nodeSelector: {}
tolerations: []
affinity: {}
```

Clone the project:

```sh
mkdir roundcube
git clone https://gitlab.terhaak.de/jojo/roundcube-docker.git roundcube
cd roundcube/helm-charts
```

```sh
helm install --namespace roundcube -f ../../roundcube-values.yml roundcube
```

Create the config file:

```sh
vim /media/cephfs/pvs/roundcube/roundcube/config/config.inc.php
```

Make sure to log to stdout:

```php
$config['log_driver'] = 'stdout';
```

Restart the pod:

```
kubectl -n prod get pods
kubectl -n prod delete pod/my-roundcube-pod
```

Get the allocated `NodePort`:

```sh
echo "$(kubectl get --namespace roundcube -o jsonpath="{.items[*].spec.ports[0].nodePort}" svc --selector=app=roundcube)"
```

Example Apache httpd proxy config:

```xml
<Proxy balancer://roundcube>
    BalancerMember http://kubernetes-node1:32100
    BalancerMember http://kubernetes-node2:32100
    BalancerMember http://kubernetes-node3:32100
    ProxySet lbmethod=byrequests
</Proxy>

RedirectPermanent "/roundcube" "https://mysite.foo/roundcube/"
<LocationMatch "^/roundcube/(.*)$" >
    ProxyPass "balancer://roundcube/$1"
    ProxyPassReverse "balancer://roundcube/$1"
</LocationMatch>
```
