import { BIDSSharedMemoryData } from "./BIDSSharedMemoryData";

export type FieldInfo<TPath extends string, TFieldType extends FieldType> = {
	_path: TPath;
	_type: TFieldType;
};
export const FIELD_TYPE = {
	NUMBER: "number",
	STRING: "string",
	BOOLEAN: "boolean",
	OBJECT: "object",
} as const;
type FieldType = (typeof FIELD_TYPE)[keyof typeof FIELD_TYPE];

export const BIDSSharedMemoryDataFieldInfo = getFields<
	"BSMD",
	BIDSSharedMemoryData
>("BSMD", {
	IsEnabled: FIELD_TYPE.BOOLEAN,
	VersionNum: FIELD_TYPE.NUMBER,
	Spec: getFields("BSMD.Spec", {
		BrakeNotchCount: FIELD_TYPE.NUMBER,
		PowerNotchCount: FIELD_TYPE.NUMBER,
		AtsCheckBrakePos: FIELD_TYPE.NUMBER,
		B67Pos: FIELD_TYPE.NUMBER,
		CarCount: FIELD_TYPE.NUMBER,
	}),
	State: getFields("BSMD.State", {
		Location_m: FIELD_TYPE.NUMBER,
		Speed_kmph: FIELD_TYPE.NUMBER,
		Time_ms: FIELD_TYPE.NUMBER,
		BCPressure_kpa: FIELD_TYPE.NUMBER,
		MRPressure_kpa: FIELD_TYPE.NUMBER,
		ERPressure_kpa: FIELD_TYPE.NUMBER,
		BPPressure_kpa: FIELD_TYPE.NUMBER,
		SAPPressure_kpa: FIELD_TYPE.NUMBER,
		Current_A: FIELD_TYPE.NUMBER,
	}),
	Handle: getFields("BSMD.Handle", {
		BrakePos: FIELD_TYPE.NUMBER,
		PowerPos: FIELD_TYPE.NUMBER,
		ReverserPos: FIELD_TYPE.NUMBER,
		ConstantSpeedControlState: FIELD_TYPE.NUMBER,
	}),
	IsDoorClosed: FIELD_TYPE.BOOLEAN,
});

type FieldTypeFromType<T> = T extends number
	? typeof FIELD_TYPE.NUMBER
	: T extends string
	? typeof FIELD_TYPE.STRING
	: T extends boolean
	? typeof FIELD_TYPE.BOOLEAN
	: T extends object
	? typeof FIELD_TYPE.OBJECT
	: never;

type FieldInfoMap<TBasePath extends string, TObj extends object> = FieldInfo<
	TBasePath,
	typeof FIELD_TYPE.OBJECT
> & {
	[TField in Extract<keyof TObj, string>]: TObj[TField] extends object
		? FieldInfoMap<`${TBasePath}.${TField}`, TObj[TField]>
		: FieldInfo<`${TBasePath}.${TField}`, FieldTypeFromType<TObj[TField]>>;
};

function getFields<TPath extends string, TObj extends object>(
	path: TPath,
	fields: {
		[TField in Extract<keyof TObj, string>]: TObj[TField] extends object
			? FieldInfoMap<`${TPath}.${TField}`, TObj[TField]>
			: FieldTypeFromType<TObj[TField]>;
	}
) {
	const entries = Object.entries(fields).map(([field, type]) => [
		field,
		typeof type === "object"
			? type
			: ({
					_path: `${path}.${field}`,
					_type: type as FieldType,
			  } satisfies FieldInfo<`${TPath}.${string}`, FieldType>),
	]);
	const pushValue = <
		T extends keyof FieldInfo<TPath, typeof FIELD_TYPE.OBJECT>
	>(
		field: T,
		value: FieldInfo<TPath, typeof FIELD_TYPE.OBJECT>[T]
	) => entries.push([field, value]);

	pushValue("_path", path);
	pushValue("_type", FIELD_TYPE.OBJECT);

	return Object.fromEntries(entries) as FieldInfoMap<TPath, TObj>;
}
