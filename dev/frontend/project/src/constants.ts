export const IS_LOCAL_DEBUG = import.meta.env.MODE === "local_debug";
export const IS_DOCKER_DEBUG = import.meta.env.MODE === "docker_debug";

export const IS_DEBUG = IS_LOCAL_DEBUG || IS_DOCKER_DEBUG;

export const APP_NAME_MAP = {
	"019552c4-e11c-71e6-b9db-49e519cfa89c": "TRViS",
	"019552c8-083b-7325-954f-3efdd8f1d525": "Client Type: BveEX",
	"019552c8-6759-738f-93aa-d3e652561e72": "Client Type: OpenBVE",
	"019552c8-cb81-7254-a810-6a5d8c0abd39": "Client Type: TRAIN CREW",
};
