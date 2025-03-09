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
		</div>
	);
});
