export const SPEC_DATA_BYTES = 20;
export type Spec = {
	/**
	 * ブレーキ段数
	 * @type integer
	 */
	BrakeNotchCount: number;

	/**
	 * 力行段数
	 * @type integer
	 */
	PowerNotchCount: number;

	/**
	 * ATS確認ブレーキ段数
	 * @type integer
	 */
	AtsCheckBrakePos: number;

	/**
	 * 常用最大段数
	 * @type integer
	 */
	B67Pos: number;

	/**
	 * 編成車両数
	 * @type integer
	 */
	CarCount: number;
};

export const STATE_DATA_BYTES = 40;
export type State = {
	/**
	 * 列車位置[m]
	 * @type double
	 */
	Location_m: number;

	/**
	 * 列車速度[km/h]
	 * @type single
	 */
	Speed_kmph: number;

	/**
	 * 現在時刻[ms] (0時からの経過時間)
	 * @type integer
	 */
	Time_ms: number;

	/**
	 * BC圧力[kPa]
	 * @type single
	 */
	BCPressure_kpa: number;
	/**
	 * MR圧力[kPa]
	 * @type single
	 */
	MRPressure_kpa: number;
	/**
	 * ER圧力[kPa]
	 * @type single
	 */
	ERPressure_kpa: number;
	/**
	 * BP圧力[kPa]
	 * @type single
	 */
	BPPressure_kpa: number;
	/**
	 * SAP圧力[kPa]
	 * @type single
	 */
	SAPPressure_kpa: number;

	/**
	 * 電流[A]
	 * @type single
	 */
	Current_A: number;
};

export const HANDLE_DATA_BYTES = 16;
export type Handle = {
	/**
	 * ブレーキハンドル位置
	 * @type integer
	 */
	BrakePos: number;

	/**
	 * 力行ハンドル位置
	 * @type integer
	 */
	PowerPos: number;

	/**
	 * レバーサー位置
	 * @type integer
	 */
	ReverserPos: number | ReverserPos;

	/**
	 * 定速制御状態
	 * @type integer
	 */
	ConstantSpeedControlState: number;
};
export const REVERSER_POS = {
	Forward: 1,
	Neutral: 0,
	Backward: -1,
} as const;
export type ReverserPos = (typeof REVERSER_POS)[keyof typeof REVERSER_POS];

const IS_ENABLED_SIZE = 1;
const VERSION_NUM_SIZE = 4;
const IS_DOOR_CLOSED_SIZE = 1;
export const BIDS_SHARED_MEMORY_DATA_BYTES =
	IS_ENABLED_SIZE +
	VERSION_NUM_SIZE +
	SPEC_DATA_BYTES +
	STATE_DATA_BYTES +
	HANDLE_DATA_BYTES +
	IS_DOOR_CLOSED_SIZE;
export type BIDSSharedMemoryData = {
	IsEnabled: boolean;
	VersionNum: number;
	Spec: Spec;
	State: State;
	Handle: Handle;
	IsDoorClosed: boolean;
};

const VERSION_NUM_OFFSET = IS_ENABLED_SIZE;
const SPEC_DATA_OFFSET = VERSION_NUM_OFFSET + VERSION_NUM_SIZE;
const STATE_DATA_OFFSET = SPEC_DATA_OFFSET + SPEC_DATA_BYTES;
const HANDLE_DATA_OFFSET = STATE_DATA_OFFSET + STATE_DATA_BYTES;
const DOOR_CLOSED_OFFSET = HANDLE_DATA_OFFSET + HANDLE_DATA_BYTES;
export function serializeBIDSSharedMemoryData(
	data: BIDSSharedMemoryData,
	buf: DataView
) {
	buf.setUint8(0, data.IsEnabled ? 1 : 0);
	buf.setUint32(VERSION_NUM_OFFSET, data.VersionNum, true);
	serializeBIDSSharedMemoryDataSpec(
		data.Spec,
		new DataView(
			buf.buffer.slice(SPEC_DATA_OFFSET, SPEC_DATA_OFFSET + SPEC_DATA_BYTES)
		)
	);
	serializeBIDSSharedMemoryDataState(
		data.State,
		new DataView(
			buf.buffer.slice(STATE_DATA_OFFSET, STATE_DATA_OFFSET + STATE_DATA_BYTES)
		)
	);
	serializeBIDSSharedMemoryDataHandle(
		data.Handle,
		new DataView(
			buf.buffer.slice(
				HANDLE_DATA_OFFSET,
				HANDLE_DATA_OFFSET + HANDLE_DATA_BYTES
			)
		)
	);
	buf.setUint8(DOOR_CLOSED_OFFSET, data.IsDoorClosed ? 1 : 0);
}
export function serializeBIDSSharedMemoryDataSpec(data: Spec, buf: DataView) {
	buf.setUint32(0, data.BrakeNotchCount, true);
	buf.setUint32(4, data.PowerNotchCount, true);
	buf.setUint32(8, data.AtsCheckBrakePos, true);
	buf.setUint32(12, data.B67Pos, true);
	buf.setUint32(16, data.CarCount, true);
}
export function serializeBIDSSharedMemoryDataState(data: State, buf: DataView) {
	buf.setFloat64(0, data.Location_m, true);
	buf.setFloat32(8, data.Speed_kmph, true);
	buf.setUint32(12, data.Time_ms, true);
	buf.setFloat32(16, data.BCPressure_kpa, true);
	buf.setFloat32(20, data.MRPressure_kpa, true);
	buf.setFloat32(24, data.ERPressure_kpa, true);
	buf.setFloat32(28, data.BPPressure_kpa, true);
	buf.setFloat32(32, data.SAPPressure_kpa, true);
	buf.setFloat32(36, data.Current_A, true);
}
export function serializeBIDSSharedMemoryDataHandle(
	data: Handle,
	buf: DataView
) {
	buf.setInt32(0, data.BrakePos, true);
	buf.setInt32(4, data.PowerPos, true);
	buf.setInt32(8, data.ReverserPos, true);
	buf.setInt32(12, data.ConstantSpeedControlState, true);
}

export function deserializeBIDSSharedMemoryData(
	buf: DataView
): BIDSSharedMemoryData {
	return {
		IsEnabled: buf.getUint8(0) !== 0,
		VersionNum: buf.getUint32(VERSION_NUM_OFFSET, true),
		Spec: deserializeBIDSSharedMemoryDataSpec(
			new DataView(
				buf.buffer.slice(SPEC_DATA_OFFSET, SPEC_DATA_OFFSET + SPEC_DATA_BYTES)
			)
		),
		State: deserializeBIDSSharedMemoryDataState(
			new DataView(
				buf.buffer.slice(
					STATE_DATA_OFFSET,
					STATE_DATA_OFFSET + STATE_DATA_BYTES
				)
			)
		),
		Handle: deserializeBIDSSharedMemoryDataHandle(
			new DataView(
				buf.buffer.slice(
					HANDLE_DATA_OFFSET,
					HANDLE_DATA_OFFSET + HANDLE_DATA_BYTES
				)
			)
		),
		IsDoorClosed: buf.getUint8(DOOR_CLOSED_OFFSET) !== 0,
	};
}
export function deserializeBIDSSharedMemoryDataSpec(buf: DataView): Spec {
	return {
		BrakeNotchCount: buf.getUint32(0, true),
		PowerNotchCount: buf.getUint32(4, true),
		AtsCheckBrakePos: buf.getUint32(8, true),
		B67Pos: buf.getUint32(12, true),
		CarCount: buf.getUint32(16, true),
	};
}
export function deserializeBIDSSharedMemoryDataState(buf: DataView): State {
	return {
		Location_m: buf.getFloat64(0, true),
		Speed_kmph: buf.getFloat32(8, true),
		Time_ms: buf.getUint32(12, true),
		BCPressure_kpa: buf.getFloat32(16, true),
		MRPressure_kpa: buf.getFloat32(20, true),
		ERPressure_kpa: buf.getFloat32(24, true),
		BPPressure_kpa: buf.getFloat32(28, true),
		SAPPressure_kpa: buf.getFloat32(32, true),
		Current_A: buf.getFloat32(36, true),
	};
}
export function deserializeBIDSSharedMemoryDataHandle(buf: DataView): Handle {
	return {
		BrakePos: buf.getInt32(0, true),
		PowerPos: buf.getInt32(4, true),
		ReverserPos: buf.getInt32(8, true),
		ConstantSpeedControlState: buf.getInt32(12, true),
	};
}
