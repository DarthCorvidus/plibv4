#!/bin/bash

# Registry
REGISTRY="default-route-openshift-image-registry.apps-crc.testing"
NAMESPACE="jenkins-agents"
docker login -u kubeadmin -p $(oc whoami -t) $REGISTRY
oc project $NAMESPACE

IMAGES=(
  "debian:11"
  "debian:12"
  "debian:13"
  "centos:9"
  "centos:10"
)

for img in "${IMAGES[@]}"; do
  distro="${img%%:*}"   # alles vor dem :
  version="${img##*:}"  # alles nach dem :
  path="dockerfiles/${distro}/${version}/"
  tag="${REGISTRY}/${NAMESPACE}/plibv4-${distro}:${version}"

  echo ">>> Building ${tag} from ${path}"
  docker build "$path" -t "$tag"
  docker push "$tag"
done
