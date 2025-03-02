export const IS_LOCAL_DEBUG = import.meta.env.MODE === "local_debug";
export const IS_DOCKER_DEBUG = import.meta.env.MODE === "docker_debug";

export const IS_DEBUG = IS_LOCAL_DEBUG || IS_DOCKER_DEBUG;
