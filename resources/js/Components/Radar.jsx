import React, { useEffect, useState, useMemo, useRef } from "react";
import ReactEcharts from "echarts-for-react";
import {
    Card,
    Col,
    Empty,
    message,
    Row,
    Skeleton,
    Space,
    Table,
    Tag,
    Tooltip,
    Badge,
    Button,
} from "antd";
import {
    FullscreenOutlined,
    FullscreenExitOutlined,
} from "@ant-design/icons";
import axios from "axios";
import dayjs from "dayjs";
import qs from "qs";
import "../../css/additional.css";

// 8 Standard Foresight Dimensions with their central polar angles (0 deg = East, 90 deg = North)
const DIMENSION_CONFIGS = [
    { name: "Economy", angle: 90, color: "#faad14" },
    { name: "Competitor", angle: 45, color: "#f5222d" },
    { name: "Customer", angle: 0, color: "#ff4d4f" },
    { name: "Supplier", angle: 315, color: "#faad14" },
    { name: "Substitute", angle: 270, color: "#fa8c16" },
    { name: "Technology", angle: 225, color: "#13c2c2" },
    { name: "Regulation", angle: 180, color: "#722ed1" },
    { name: "Ecology", angle: 135, color: "#52c41a" },
];

const DIMENSION_ANGLES = DIMENSION_CONFIGS.reduce((acc, cur) => {
    acc[cur.name] = cur.angle;
    return acc;
}, {});

function getPriorityColor(priority) {
    const p = (priority || "").toLowerCase();
    if (p === "high") return "#ff4d4f";
    if (p === "medium") return "#faad14";
    if (p === "low") return "#52c41a";
    return "#1677ff";
}

function getPriorityTagColor(priority) {
    const p = (priority || "").toLowerCase();
    if (p === "high") return "error";
    if (p === "medium") return "warning";
    if (p === "low") return "success";
    return "default";
}

/**
 * Intelligent anti-collision layout algorithm.
 * Groups points by dimension and time horizon, fans them out within the 45-degree sector,
 * and runs a 2D Euclidean relaxation pass so markers never overlap.
 */
function computeAntiCollisionPoints(items) {
    if (!items || items.length === 0) return [];

    const groups = {};

    items.forEach((item) => {
        // Determine horizon: 1 = Short Term, 2 = Mid Term, 3 = Long Term
        let horizon = 2;
        if (item.short_term === "✔" || item.time_horizon_id === 1) {
            horizon = 1;
        } else if (item.long_term === "✔" || item.time_horizon_id === 3) {
            horizon = 3;
        } else if (item.mid_term === "✔" || item.time_horizon_id === 2) {
            horizon = 2;
        } else if (Array.isArray(item.value)) {
            const val = item.value.find((v) => v > 0);
            if (val && val <= 4) horizon = 1;
            else if (val && val <= 6) horizon = 2;
            else if (val) horizon = 3;
        }

        // Base radial position for horizon ring
        let baseRadius = 5.5;
        if (horizon === 1) {
            baseRadius = 2.8; // Inner band: 2.2 - 3.8
        } else if (horizon === 2) {
            baseRadius = 5.4; // Middle band: 4.8 - 6.2
        } else {
            baseRadius = 7.6; // Outer band: 7.0 - 8.4
        }

        // Incorporate backend numeric value if present
        if (Array.isArray(item.value)) {
            const rawVal = item.value.find((v) => v > 0);
            if (rawVal) {
                if (horizon === 1) {
                    baseRadius = 2.3 + ((rawVal - 2) / 2) * 1.3;
                } else if (horizon === 2) {
                    baseRadius = 4.7 + ((rawVal - 5) / 1) * 1.2;
                } else {
                    baseRadius = 7.1 + ((rawVal - 7) / 1) * 1.2;
                }
            }
        }

        const key = `${item.dimension}_${horizon}`;
        if (!groups[key]) groups[key] = [];
        groups[key].push({
            ...item,
            horizon,
            baseRadius,
        });
    });

    const dispersed = [];

    // Fan-out points in each cluster
    Object.entries(groups).forEach(([key, groupItems]) => {
        const dimName = groupItems[0].dimension;
        const centerAngle = DIMENSION_ANGLES[dimName] ?? 0;
        const count = groupItems.length;

        let angleOffsets = [0];
        let radiusOffsets = [0];

        if (count === 2) {
            // Arc separation of 20 deg
            angleOffsets = [-10, 10];
            radiusOffsets = [-0.25, 0.25];
        } else if (count === 3) {
            // Triangle constellation
            angleOffsets = [-14, 0, 14];
            radiusOffsets = [0.35, -0.35, 0.35];
        } else if (count === 4) {
            // Diamond / quadrilateral constellation
            angleOffsets = [-16, -6, 6, 16];
            radiusOffsets = [-0.35, 0.35, -0.35, 0.35];
        } else if (count > 4) {
            // Even fan across up to 34 degrees
            const span = 34;
            const step = span / (count - 1);
            angleOffsets = groupItems.map((_, i) => -span / 2 + i * step);
            radiusOffsets = groupItems.map((_, i) => (i % 2 === 0 ? -0.35 : 0.35));
        }

        groupItems.forEach((item, idx) => {
            const finalAngle = (centerAngle + angleOffsets[idx] + 360) % 360;
            const finalRadius = Math.max(1.8, Math.min(8.6, item.baseRadius + radiusOffsets[idx]));

            dispersed.push({
                ...item,
                angle: finalAngle,
                radius: finalRadius,
                centerAngle,
            });
        });
    });

    // 2D Euclidean relaxation pass to guarantee zero overlap
    const MIN_DIST = 0.95; // Radius units (approx 32px on screen)
    for (let iter = 0; iter < 12; iter++) {
        let hasOverlap = false;
        for (let i = 0; i < dispersed.length; i++) {
            for (let j = i + 1; j < dispersed.length; j++) {
                const p1 = dispersed[i];
                const p2 = dispersed[j];
                if (p1.dimension !== p2.dimension) continue;

                const rad1 = (p1.angle * Math.PI) / 180;
                const rad2 = (p2.angle * Math.PI) / 180;
                const x1 = p1.radius * Math.cos(rad1);
                const y1 = p1.radius * Math.sin(rad1);
                const x2 = p2.radius * Math.cos(rad2);
                const y2 = p2.radius * Math.sin(rad2);

                const dx = x2 - x1;
                const dy = y2 - y1;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < MIN_DIST && dist > 0.001) {
                    hasOverlap = true;
                    const overlap = (MIN_DIST - dist) / 2;
                    const avgR = Math.max(2, (p1.radius + p2.radius) / 2);
                    const angleShift = (overlap / avgR) * (180 / Math.PI);

                    if (p1.angle <= p2.angle) {
                        p1.angle = Math.max(p1.centerAngle - 18, p1.angle - angleShift);
                        p2.angle = Math.min(p2.centerAngle + 18, p2.angle + angleShift);
                    } else {
                        p1.angle = Math.min(p1.centerAngle + 18, p1.angle + angleShift);
                        p2.angle = Math.max(p1.centerAngle - 18, p2.angle - angleShift);
                    }
                }
            }
        }
        if (!hasOverlap) break;
    }

    return dispersed;
}

export default function Radar({ loading, setLoading, date, selectData }) {
    const [data, setData] = useState([]);
    const [activeRowKey, setActiveRowKey] = useState(null);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const echartsRef = useRef(null);

    // Support ESC key to exit fullscreen mode
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === "Escape" && isFullscreen) {
                setIsFullscreen(false);
            }
        };
        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [isFullscreen]);

    const [tableParams] = useState({
        date: [
            dayjs().startOf("month").format("YYYY-MM-DD"),
            dayjs().endOf("month").format("YYYY-MM-DD"),
        ],
    });

    useEffect(() => {
        fetchData();
    }, [date, selectData]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const queryParams = {
                ...tableParams,
                date: date != null ? date.date : tableParams.date,
                dimension: selectData ? selectData.dimension : null,
            };

            const response = await axios.get(
                `${route("visualization.foresight-radar.get-data")}?${qs.stringify(queryParams)}`
            );

            if (response.status === 200 && Array.isArray(response.data)) {
                const newData = response.data.map((d, i) => ({
                    no: i + 1,
                    ...d,
                }));
                setData(newData);
            } else {
                message.error("Failed to load radar data");
            }
        } catch (error) {
            message.error(`Error: ${error.message || "Failed to fetch data"}`);
        } finally {
            setLoading(false);
        }
    };

    // Calculate collision-free coordinates
    const dispersedData = useMemo(() => {
        return computeAntiCollisionPoints(data);
    }, [data]);

    // Construct scatter series data for ECharts polar chart
    const scatterData = useMemo(() => {
        return dispersedData.map((p) => {
            const pColor = p.item_style || getPriorityColor(p.priority);
            const horizonName =
                p.horizon === 1
                    ? "Short Term (0 - 1 yr)"
                    : p.horizon === 2
                    ? "Mid Term (1 - 3 yrs)"
                    : "Long Term (> 3 yrs)";

            return {
                value: [p.radius, p.angle],
                id: p.no,
                name: p.keyword,
                dimension: p.dimension,
                priority: p.priority,
                horizon: horizonName,
                statusAction: p.status_action,
                symbol: p.symbol || "circle",
                itemStyle: {
                    color: pColor,
                    borderColor: "#ffffff",
                    borderWidth: 1.5,
                    shadowBlur: 4,
                    shadowColor: "rgba(0,0,0,0.25)",
                },
            };
        });
    }, [dispersedData]);

    // Highlight marker when table row is hovered
    const handleRowMouseEnter = (record) => {
        setActiveRowKey(record.no);
        if (echartsRef.current) {
            const instance = echartsRef.current.getEchartsInstance();
            const dataIndex = scatterData.findIndex((d) => d.id === record.no);
            if (dataIndex >= 0) {
                instance.dispatchAction({
                    type: "highlight",
                    seriesIndex: 0,
                    dataIndex,
                });
                instance.dispatchAction({
                    type: "showTip",
                    seriesIndex: 0,
                    dataIndex,
                });
            }
        }
    };

    const handleRowMouseLeave = () => {
        setActiveRowKey(null);
        if (echartsRef.current) {
            const instance = echartsRef.current.getEchartsInstance();
            instance.dispatchAction({
                type: "downplay",
                seriesIndex: 0,
            });
            instance.dispatchAction({
                type: "hideTip",
            });
        }
    };

    // ECharts Configuration
    const options = useMemo(() => {
        return {
            title: {
                show: false,
            },
            tooltip: {
                trigger: "item",
                backgroundColor: "rgba(255, 255, 255, 0.98)",
                borderColor: "#e8e8e8",
                borderWidth: 1,
                extraCssText:
                    "box-shadow: 0 6px 16px 0 rgba(0, 0, 0, 0.12); border-radius: 8px; padding: 12px;",
                formatter: function (params) {
                    const d = params.data;
                    if (!d) return "";
                    const pColor = getPriorityColor(d.priority);
                    return `
                        <div style="font-weight: 700; font-size: 14px; margin-bottom: 6px; color: #111827;">
                            #${d.id} ${d.name}
                        </div>
                        <div style="font-size: 12px; color: #4b5563; line-height: 1.8;">
                            <div>Dimension: <b style="color: #111827;">${d.dimension}</b></div>
                            <div>Horizon: <b style="color: #111827;">${d.horizon}</b></div>
                            <div>Priority: <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${pColor};margin-right:6px;"></span><b style="color:${pColor};">${d.priority}</b></div>
                            ${
                                d.statusAction
                                    ? `<div>Action: <b style="color: #111827;">${d.statusAction}</b></div>`
                                    : ""
                            }
                        </div>
                    `;
                },
            },
            polar: {
                radius: isFullscreen ? "80%" : "76%",
                center: ["50%", "47%"],
            },
            angleAxis: {
                type: "value",
                min: 0,
                max: 360,
                startAngle: 0,
                clockwise: false,
                interval: 45,
                axisLine: {
                    lineStyle: {
                        color: "#8c8c8c",
                    },
                },
                splitLine: {
                    show: true,
                    lineStyle: {
                        color: "#bfbfbf",
                        width: 1,
                        type: "dashed",
                    },
                },
                axisTick: {
                    show: false,
                },
                axisLabel: {
                    show: true,
                    formatter: function (val) {
                        const dim = DIMENSION_CONFIGS.find(
                            (d) => Math.abs(d.angle - (val % 360)) < 1
                        );
                        return dim ? dim.name : "";
                    },
                    color: "#1f2937",
                    fontWeight: "700",
                    fontSize: 12,
                    padding: [4, 8],
                    backgroundColor: "#ffffff",
                    borderRadius: 6,
                    borderColor: "#d1d5db",
                    borderWidth: 1,
                    shadowBlur: 4,
                    shadowColor: "rgba(0,0,0,0.06)",
                    shadowOffsetY: 1,
                },
            },
            radiusAxis: {
                min: 0,
                max: 9,
                interval: 1,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { show: false },
                splitLine: {
                    show: true,
                    lineStyle: {
                        color: "#a0a0a0",
                        width: 1,
                    },
                },
                splitArea: {
                    show: true,
                    areaStyle: {
                        color: [
                            "#9e9e9e",
                            "#9e9e9e",
                            "#9e9e9e",
                            "#9e9e9e",
                            "#bcbcbc",
                            "#bcbcbc",
                            "#dcdcdc",
                            "#dcdcdc",
                        ],
                    },
                },
            },
            series: [
                {
                    name: "Signals",
                    type: "scatter",
                    coordinateSystem: "polar",
                    symbolSize: 26,
                    data: scatterData,
                    label: {
                        show: true,
                        formatter: function (params) {
                            return params.data.id;
                        },
                        position: "inside",
                        color: "#ffffff",
                        fontWeight: "bold",
                        fontSize: 11,
                    },
                    emphasis: {
                        scale: 1.25,
                        itemStyle: {
                            shadowBlur: 10,
                            shadowColor: "rgba(0,0,0,0.4)",
                        },
                    },
                },
            ],
        };
    }, [scatterData, isFullscreen]);

    const columns = [
        {
            title: "No",
            dataIndex: "no",
            width: 55,
            align: "center",
            render: (val, record) => (
                <span
                    className="radar-no-badge"
                    style={{ backgroundColor: getPriorityColor(record.priority) }}
                >
                    {val}
                </span>
            ),
        },
        {
            title: "Signal of Changes",
            dataIndex: "keyword",
            render: (text, record) => (
                <div>
                    <div style={{ fontWeight: 600, color: "#1f2937", fontSize: 13 }}>
                        {text}
                    </div>
                    <div style={{ marginTop: 2, display: "flex", gap: 4, flexWrap: "wrap" }}>
                        <Tag
                            bordered={false}
                            style={{
                                fontSize: 11,
                                lineHeight: "18px",
                                padding: "0 6px",
                                backgroundColor: "#f3f4f6",
                                color: "#4b5563",
                            }}
                        >
                            {record.dimension}
                        </Tag>
                        {record.status_action && (
                            <Tag
                                bordered={false}
                                color="blue"
                                style={{ fontSize: 11, lineHeight: "18px", padding: "0 6px" }}
                            >
                                {record.status_action}
                            </Tag>
                        )}
                    </div>
                </div>
            ),
        },
        {
            title: "Priority",
            dataIndex: "priority",
            width: 90,
            align: "center",
            render: (priority) => (
                <Tag
                    color={getPriorityTagColor(priority)}
                    bordered={false}
                    style={{
                        fontWeight: 600,
                        borderRadius: 12,
                        padding: "2px 8px",
                        fontSize: 11,
                    }}
                >
                    {priority}
                </Tag>
            ),
        },
    ];

    return (
        <div className={isFullscreen ? "radar-fullscreen" : ""}>
            <Row gutter={[16, 16]} style={{ margin: 0, height: "100%" }}>
                {/* Left Card: Signal List */}
                <Col xs={24} lg={10} xl={9}>
                    <Card
                        className="radar-card"
                        style={{ height: "100%", display: "flex", flexDirection: "column" }}
                        bodyStyle={{ padding: 12, flex: 1, display: "flex", flexDirection: "column" }}
                        title={
                            <Space>
                                <span style={{ fontWeight: 600 }}>Signal of Changes</span>
                                <Badge
                                    count={data.length}
                                    overflowCount={999}
                                    style={{ backgroundColor: "#1677ff" }}
                                />
                            </Space>
                        }
                    >
                        <Table
                            columns={columns}
                            dataSource={data}
                            loading={loading}
                            size="small"
                            rowKey={(record) => record.no}
                            pagination={false}
                            scroll={{ y: isFullscreen ? "calc(100vh - 220px)" : 460 }}
                            rowClassName={(record) =>
                                `radar-table-row ${record.no === activeRowKey ? "row-active" : ""}`
                            }
                            onRow={(record) => ({
                                onMouseEnter: () => handleRowMouseEnter(record),
                                onMouseLeave: () => handleRowMouseLeave(),
                            })}
                            locale={{
                                emptyText: (
                                    <Empty
                                        image={Empty.PRESENTED_IMAGE_SIMPLE}
                                        description="No signals found for the selected period or dimension"
                                    />
                                ),
                            }}
                        />
                    </Card>
                </Col>

                {/* Right Card: Radar Visualization */}
                <Col xs={24} lg={14} xl={15}>
                    <Card
                        className="radar-card"
                        style={{ height: "100%", display: "flex", flexDirection: "column" }}
                        bodyStyle={{
                            padding: "12px 16px 16px",
                            flex: 1,
                            display: "flex",
                            flexDirection: "column",
                            justifyContent: "space-between",
                        }}
                        title={
                            <Space>
                                <span style={{ fontWeight: 600 }}>Radar Visualization</span>
                                {selectData?.dimension && (
                                    <Tag color="processing">Filtered by Dimension</Tag>
                                )}
                            </Space>
                        }
                        extra={
                            <Space>
                                <Tooltip
                                    title={
                                        isFullscreen
                                            ? "Keluar Layar Penuh (ESC)"
                                            : "Perbesar ke Layar Penuh (Fullscreen)"
                                    }
                                >
                                    <Button
                                        type="text"
                                        size="small"
                                        icon={
                                            isFullscreen ? (
                                                <FullscreenExitOutlined />
                                            ) : (
                                                <FullscreenOutlined />
                                            )
                                        }
                                        onClick={() => setIsFullscreen(!isFullscreen)}
                                        style={{ fontWeight: 500 }}
                                    >
                                        {isFullscreen ? "Kecilkan" : "Layar Penuh"}
                                    </Button>
                                </Tooltip>
                            </Space>
                        }
                    >
                        <Skeleton loading={loading} active orientation="vertical">
                            {data.length === 0 ? (
                                <div style={{ padding: "120px 0", textAlign: "center" }}>
                                    <Empty description="No radar signals available to display" />
                                </div>
                            ) : (
                                <>
                                    <ReactEcharts
                                        ref={echartsRef}
                                        notMerge={true}
                                        option={options}
                                        style={{
                                            height: isFullscreen
                                                ? "calc(100vh - 200px)"
                                                : "500px",
                                            width: "100%",
                                        }}
                                    />

                                    {/* Concentric Ring Legend */}
                                    <div className="radar-ring-legend">
                                        <span className="radar-ring-pill">
                                            <span
                                                className="radar-ring-circle"
                                                style={{ background: "#9e9e9e" }}
                                            />
                                            Short Term (0 - 1 yr)
                                        </span>
                                        <span className="radar-ring-pill">
                                            <span
                                                className="radar-ring-circle"
                                                style={{ background: "#bcbcbc" }}
                                            />
                                            Mid Term (1 - 3 yrs)
                                        </span>
                                        <span className="radar-ring-pill">
                                            <span
                                                className="radar-ring-circle"
                                                style={{ background: "#dcdcdc" }}
                                            />
                                            Long Term (&gt; 3 yrs)
                                        </span>
                                    </div>

                                    {/* Priority Legend */}
                                    <div className="radar-legend-bar">
                                        <span className="radar-legend-item">
                                            <span
                                                className="radar-legend-color"
                                                style={{ background: "#ff4d4f" }}
                                            />
                                            High Priority
                                        </span>
                                        <span className="radar-legend-item">
                                            <span
                                                className="radar-legend-color"
                                                style={{ background: "#faad14" }}
                                            />
                                            Medium Priority
                                        </span>
                                        <span className="radar-legend-item">
                                            <span
                                                className="radar-legend-color"
                                                style={{ background: "#52c41a" }}
                                            />
                                            Low Priority
                                        </span>
                                    </div>
                                </>
                            )}
                        </Skeleton>
                    </Card>
                </Col>
            </Row>
        </div>
    );
}
