import { BIDSSharedMemoryData } from "./BIDSSharedMemoryData";

export type FieldInfo<
	TPath extends string,
	TValueType,
	TFieldType extends FieldType = FieldTypeFromType<TValueType>
> = {
	_path: TPath;
	_type: TFieldType;
	_equal: (a: TValueType, b: TValueType) => boolean;
	__valueFieldForTypeRef?: TValueType;
};
export const FIELD_TYPE = {
	NUMBER: "number",
	STRING: "string",
	BOOLEAN: "boolean",
	OBJECT: "object",
} as const;
type FieldType = (typeof FIELD_TYPE)[keyof typeof FIELD_TYPE];
const _fieldTypeIdSet = new Set(Object.values(FIELD_TYPE));
function isFieldType(v: unknown): v is FieldType {
	return _fieldTypeIdSet.has(v as FieldType);
}

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
	TObj,
	typeof FIELD_TYPE.OBJECT
> &
	FieldInfoMapChildren<TBasePath, TObj>;
type FieldInfoMapChildren<TBasePath extends string, TObj extends object> = {
	[TField in Extract<keyof TObj, string>]: TObj[TField] extends object
		? FieldInfoMap<`${TBasePath}.${TField}`, TObj[TField]>
		: FieldInfo<`${TBasePath}.${TField}`, TObj[TField]>;
};

type GetFieldsArgType<TPath extends string, TObj extends object> = {
	[TField in Extract<keyof TObj, string>]: TObj[TField] extends object
		? FieldInfoMap<`${TPath}.${TField}`, TObj[TField]>
		: FieldTypeFromType<TObj[TField]>;
};
function getFields<TPath extends string, TObj extends object>(
	path: TPath,
	fields: GetFieldsArgType<TPath, TObj>
): FieldInfoMap<TPath, TObj> {
	const fieldInfoEntries = Object.entries(fields).map(([field, type]) => {
		const _field = field as keyof typeof fields;
		const _type = type as (typeof fields)[typeof _field];
		return convertEntries<TPath, TObj, typeof _field>(
			path,
			_field,
			// eslint-disable-next-line @typescript-eslint/no-explicit-any
			_type as any
		);
	});
	const equalFuncList = Object.values(fieldInfoEntries).map(
		([field, type]) => [field, type._equal] as const
	);
	const fieldInfoMap = Object.fromEntries(
		fieldInfoEntries
	) as unknown as FieldInfoMapChildren<TPath, TObj>;
	return {
		...fieldInfoMap,
		_path: path,
		_type: FIELD_TYPE.OBJECT,
		_equal: (a, b) => {
			for (const [field, equalFunc] of equalFuncList) {
				if (!equalFunc(a[field], b[field])) {
					return false;
				}
			}
			return true;
		},
	};
}

function convertEntries<
	TPath extends string,
	TObj extends object,
	TField extends Extract<keyof TObj, string>
>(
	path: TPath,
	field: TField,
	type: GetFieldsArgType<TField, TObj>[TField]
): [TField, FieldInfo<`${TPath}.${TField}`, TObj[TField]>] {
	if (!isFieldType(type)) {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		return [field, type as any] as const;
	}
	return [
		field,
		{
			_path: `${path}.${field}` as const,
			_type: type as FieldTypeFromType<TObj[TField]>,
			_equal: (a, b) => a === b,
		},
	] as const;
}
