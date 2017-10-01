#!/usr/bin/env bash
# If error, please stop.
set -e;
# Define variables
export VERSION="${1:-5.6}";
export TAG="${2:-reactphp/stomp}:${VERSION}";
export DIR="$(dirname $0)"

# Load Dockerfile template
DOCKER_FILE="$(< "$DIR/Dockerfile.template")";

# Build Dockerfile on the fly and build image
envsubst <<< "${DOCKER_FILE}" > Dockerfile ;
docker build -t "${TAG}" .

# Run the tests in the docker image
docker run --rm -tv "$(realpath "$DIR/../../")":/project:ro "${TAG}" /project/tests/utils/do_tests.sh;
