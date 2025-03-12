import { memo } from "react";
import { useBsmdValue } from "../bids/BidsDataHook";
import { BIDSSharedMemoryDataFieldInfo } from "../webrtc/BIDSSharedMemoryDataFieldInfo";

export default memo(function BidsDataViewer() {
	const isDoorClosed = useBsmdValue(BIDSSharedMemoryDataFieldInfo.IsDoorClosed);
	const location = useBsmdValue(BIDSSharedMemoryDataFieldInfo.State.Location_m);
	const speed = useBsmdValue(BIDSSharedMemoryDataFieldInfo.State.Speed_kmph);
	const carCount = useBsmdValue(BIDSSharedMemoryDataFieldInfo.Spec.CarCount);

	return (
		<div>
			<p>ドア閉: {isDoorClosed == null ? "???" : isDoorClosed ? "閉" : "開"}</p>
			<p>列車位置: {location ?? "???"}m</p>
			<p>列車速度: {speed ?? "???"}km/h</p>
			<p>編成車両数: {carCount ?? "???"}</p>
			<p>
				時刻: <TimeStr />
			</p>
		</div>
	);
});

const TimeStr = () => {
	const time = useBsmdValue(BIDSSharedMemoryDataFieldInfo.State.Time_ms);
	if (time == null) {
		return "???";
	}

	const hh = Math.floor(time / 3600000);
	const mm = Math.floor((time % 3600000) / 60000);
	const ss = Math.floor((time % 60000) / 1000);
	const ms = time % 1000;

	const hhStr = hh.toString().padStart(2, "0");
	const mmStr = mm.toString().padStart(2, "0");
	const ssStr = ss.toString().padStart(2, "0");
	const msStr = ms.toString().padStart(3, "0");
	return `${hhStr}:${mmStr}:${ssStr}.${msStr}`;
};
