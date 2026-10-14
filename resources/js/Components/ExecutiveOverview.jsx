import React from "react";
import ReactEcharts from "echarts-for-react";
import {
    Card,
    Row,
    Col,
    Typography,
    Tag,
    Alert,
    Skeleton,
    Empty,
    Space,
    Progress,
} from "antd";
import {
    CompassOutlined,
    SafetyCertificateOutlined,
    RocketOutlined,
    AlertOutlined,
    ThunderboltOutlined,
} from "@ant-design/icons";

const { Title, Text, Paragraph } = Typography;

export default function ExecutiveOverview({
    loading = false,
    reasoningData = null,
}) {
    if (loading && !reasoningData) {
        return (
            <div style={{ padding: "16px 0" }}>
                <Skeleton active paragraph={{ rows: 8 }} />
            </div>
        );
    }

    if (!reasoningData) {
        return (
            <div style={{ padding: "40px 0" }}>
                <Empty description="Data reasoning tidak tersedia untuk filter yang dipilih." />
            </div>
        );
    }

    const {
        growth_index = 50,
        resilience_index = 50,
        readiness_status = "Balanced",
        readiness_badge = "Moderate",
        readiness_color = "processing",
        readiness_description = "",
        total_active_forces = 0,
        top_critical_forces = [],
    } = reasoningData;

    // ECharts option for Dual Speedometer / Gauge
    const getGaugeOption = (value, title, colorStart, colorEnd) => ({
        series: [
            {
                type: "gauge",
                startAngle: 180,
                endAngle: 0,
                min: 0,
                max: 100,
                splitNumber: 5,
                radius: "95%",
                center: ["50%", "72%"],
                itemStyle: {
                    color: {
                        type: "linear",
                        x: 0,
                        y: 0,
                        x2: 1,
                        y2: 0,
                        colorStops: [
                            { offset: 0, color: colorStart },
                            { offset: 1, color: colorEnd },
                        ],
                    },
                },
                progress: {
                    show: true,
                    roundCap: true,
                    width: 14,
                },
                pointer: {
                    icon: "roundRect",
                    length: "60%",
                    width: 6,
                    offsetCenter: [0, "-5%"],
                    itemStyle: {
                        color: "#1f1f1f",
                    },
                },
                axisLine: {
                    roundCap: true,
                    lineStyle: {
                        width: 14,
                        color: [[1, "#f0f2f5"]],
                    },
                },
                axisTick: {
                    distance: -20,
                    length: 6,
                    lineStyle: {
                        color: "#bfbfbf",
                        width: 1,
                    },
                },
                splitLine: {
                    distance: -24,
                    length: 10,
                    lineStyle: {
                        color: "#8c8c8c",
                        width: 2,
                    },
                },
                axisLabel: {
                    distance: -20,
                    color: "#595959",
                    fontSize: 11,
                },
                title: {
                    offsetCenter: [0, "30%"],
                    fontSize: 14,
                    fontWeight: 600,
                    color: "#262626",
                },
                detail: {
                    valueAnimation: true,
                    offsetCenter: [0, "-18%"],
                    fontSize: 26,
                    fontWeight: 700,
                    formatter: "{value}",
                    color: "#1f1f1f",
                },
                data: [
                    {
                        value: Number(value).toFixed(1),
                        name: title,
                    },
                ],
            },
        ],
    });

    return (
        <div style={{ padding: "6px 0 16px" }}>
            {/* Top Readiness Status Banner */}
            <Alert
                message={
                    <Space size={10} align="center">
                        <CompassOutlined style={{ orientation: "center", fontSize: 18 }} />
                        <span style={{ fontSize: 15, fontWeight: 700 }}>
                            Postur Kesiapan Strategis: {readiness_status}
                        </span>
                        <Tag color={readiness_color} style={{ fontWeight: 600, borderRadius: 10 }}>
                            {readiness_badge}
                        </Tag>
                    </Space>
                }
                description={
                    <Paragraph style={{ margin: "6px 0 0", color: "#595959", fontSize: 13 }}>
                        {readiness_description} (Dievaluasi dari {total_active_forces} driving force aktif).
                    </Paragraph>
                }
                type={readiness_color === "error" ? "error" : readiness_color === "warning" ? "warning" : "info"}
                showIcon
                style={{
                    marginBottom: 18,
                    borderRadius: 8,
                    borderLeft: `4px solid ${
                        readiness_color === "error" ? "#ff4d4f" : readiness_color === "warning" ? "#faad14" : "#1677ff"
                    }`,
                }}
            />

            {/* Row 1: Dual Index Gauges */}
            <Row gutter={[16, 16]} style={{ marginBottom: 18 }}>
                <Col xs={24} lg={12}>
                    <Card
                        bordered={false}
                        style={{
                            boxShadow: "0 1px 3px rgba(0,0,0,0.05)",
                            borderRadius: 8,
                            height: "100%",
                        }}
                        bodyStyle={{ padding: "16px 20px" }}
                    >
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 4 }}>
                            <Space align="center">
                                <RocketOutlined style={{ color: "#1677ff", fontSize: 16 }} />
                                <Text strong style={{ fontSize: 15 }}>Growth Index</Text>
                            </Space>
                            <Tag color="blue" bordered={false}>Peluang Pasar</Tag>
                        </div>
                        <Text type="secondary" style={{ fontSize: 12 }}>
                            Daya dorong organisasi menangkap peluang pada tren Customer, Technology, & Economy.
                        </Text>
                        <div style={{ height: 210, marginTop: 4 }}>
                            <ReactEcharts
                                option={getGaugeOption(growth_index, "Skor Growth", "#1677ff", "#13c2c2")}
                                style={{ height: "100%", width: "100%" }}
                                notMerge={true}
                                lazyUpdate={true}
                            />
                        </div>
                        <div style={{ display: "flex", justifyContent: "space-between", fontSize: 12, color: "#8c8c8c" }}>
                            <span>0: Stagnant</span>
                            <span>50: Baseline</span>
                            <span>100: High Growth</span>
                        </div>
                    </Card>
                </Col>

                <Col xs={24} lg={12}>
                    <Card
                        bordered={false}
                        style={{
                            boxShadow: "0 1px 3px rgba(0,0,0,0.05)",
                            borderRadius: 8,
                            height: "100%",
                        }}
                        bodyStyle={{ padding: "16px 20px" }}
                    >
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 4 }}>
                            <Space align="center">
                                <SafetyCertificateOutlined style={{ color: "#52c41a", fontSize: 16 }} />
                                <Text strong style={{ fontSize: 15 }}>Resilience Index</Text>
                            </Space>
                            <Tag color="green" bordered={false}>Mitigasi Risiko</Tag>
                        </div>
                        <Text type="secondary" style={{ fontSize: 12 }}>
                            Ketahanan organisasi terhadap ancaman eksternal melalui Status Action terencana.
                        </Text>
                        <div style={{ height: 210, marginTop: 4 }}>
                            <ReactEcharts
                                option={getGaugeOption(resilience_index, "Skor Resilience", "#52c41a", "#faad14")}
                                style={{ height: "100%", width: "100%" }}
                                notMerge={true}
                                lazyUpdate={true}
                            />
                        </div>
                        <div style={{ display: "flex", justifyContent: "space-between", fontSize: 12, color: "#8c8c8c" }}>
                            <span>0: Vulnerable</span>
                            <span>50: Baseline</span>
                            <span>100: Fully Resilient</span>
                        </div>
                    </Card>
                </Col>
            </Row>

            {/* Row 2: Top Critical Driving Forces Requiring Attention */}
            <Card
                bordered={false}
                style={{
                    boxShadow: "0 1px 3px rgba(0,0,0,0.05)",
                    borderRadius: 8,
                }}
                bodyStyle={{ padding: "16px 20px" }}
            >
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
                    <Space align="center">
                        <AlertOutlined style={{ color: "#ff4d4f", fontSize: 16 }} />
                        <Text strong style={{ fontSize: 15 }}>Top Driving Forces Kritis (Perhatian BoD)</Text>
                    </Space>
                    <Tag color="volcano" bordered={false}>Perlu Mitigasi Segera</Tag>
                </div>
                <Text type="secondary" style={{ fontSize: 12, display: "block", marginBottom: 14 }}>
                    Daftar driving force dengan dampak tertinggi yang masih dalam tahap pemantauan (Monitoring) atau memiliki urgensi Prioritas High.
                </Text>

                {top_critical_forces.length === 0 ? (
                    <Empty
                        image={Empty.PRESENTED_IMAGE_SIMPLE}
                        description="Tidak ada driving force berisiko tinggi yang membutuhkan mitigasi darurat saat ini."
                    />
                ) : (
                    <Row gutter={[12, 12]}>
                        {top_critical_forces.map((force, index) => (
                            <Col xs={24} md={8} key={force.id || index}>
                                <div
                                    style={{
                                        border: "1px solid #f0f0f0",
                                        borderRadius: 8,
                                        padding: "14px 16px",
                                        backgroundColor: "#fafafa",
                                        height: "100%",
                                        display: "flex",
                                        flexDirection: "column",
                                        justifyContent: "space-between",
                                    }}
                                >
                                    <div>
                                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 6, marginBottom: 8 }}>
                                            <Tag color="cyan" style={{ margin: 0, fontSize: 11 }}>
                                                {force.dimension || "Dimensi"}
                                            </Tag>
                                            <Tag color={force.priority_color === "red" ? "error" : "warning"} style={{ margin: 0 }}>
                                                {force.priority}
                                            </Tag>
                                        </div>
                                        <Text strong style={{ fontSize: 13, display: "block", lineHeight: "1.4", color: "#1f1f1f", marginBottom: 8 }}>
                                            {force.name}
                                        </Text>
                                    </div>

                                    <div style={{ marginTop: 10, paddingTop: 10, borderTop: "1px dashed #e8e8e8" }}>
                                        <div style={{ display: "flex", justifyContent: "space-between", fontSize: 12, marginBottom: 4 }}>
                                            <Text type="secondary">Status Aksi:</Text>
                                            <Text strong style={{ color: force.status_action === "Monitoring" ? "#fa8c16" : "#52c41a" }}>
                                                {force.status_action_code} {force.status_action}
                                            </Text>
                                        </div>
                                        <div style={{ display: "flex", justifyContent: "space-between", fontSize: 12, marginBottom: 2 }}>
                                            <Text type="secondary">Impact: {force.impact}/10</Text>
                                            <Text type="secondary">Uncertainty: {force.uncertainty}/10</Text>
                                        </div>
                                        <Progress
                                            percent={force.impact * 10}
                                            size="small"
                                            status={force.impact >= 7 ? "exception" : "normal"}
                                            showInfo={false}
                                        />
                                    </div>
                                </div>
                            </Col>
                        ))}
                    </Row>
                )}
            </Card>
        </div>
    );
}
