import React from "react";
import ReactEcharts from "echarts-for-react";
import { Card, Row, Col, Typography, Empty, Skeleton, Tag, Space } from "antd";
import { BarChartOutlined, LineChartOutlined } from "@ant-design/icons";

const { Text } = Typography;

export default function EdaCharts({
    loading = false,
    edaFrequency = [],
    edaTrend = [],
}) {
    if (loading) {
        return (
            <div style={{ padding: "16px 0" }}>
                <Skeleton active paragraph={{ rows: 6 }} />
            </div>
        );
    }

    const hasFrequencyData = edaFrequency && edaFrequency.length > 0;
    const hasTrendData = edaTrend && edaTrend.length > 0;

    // Dimension Frequency Bar Chart Option
    const getFrequencyOption = () => {
        const categories = edaFrequency.map((item) => item.name);
        const decidedData = edaFrequency.map((item) => item.decided_count || 0);
        const monitoringData = edaFrequency.map((item) => item.monitoring_count || 0);

        return {
            tooltip: {
                trigger: "axis",
                axisPointer: { type: "shadow" },
            },
            legend: {
                data: ["Decided Plan (O)", "Monitoring (∆)"],
                bottom: 0,
            },
            grid: {
                top: "12%",
                left: "3%",
                right: "4%",
                bottom: "16%",
                containLabel: true,
            },
            xAxis: {
                type: "category",
                data: categories,
                axisLabel: {
                    interval: 0,
                    rotate: categories.length > 6 ? 25 : 0,
                    fontSize: 11,
                    color: "#595959",
                },
            },
            yAxis: {
                type: "value",
                minInterval: 1,
                splitLine: {
                    lineStyle: { type: "dashed", color: "#f0f0f0" },
                },
            },
            series: [
                {
                    name: "Decided Plan (O)",
                    type: "bar",
                    stack: "total",
                    emphasis: { focus: "series" },
                    itemStyle: { color: "#52c41a" },
                    data: decidedData,
                },
                {
                    name: "Monitoring (∆)",
                    type: "bar",
                    stack: "total",
                    emphasis: { focus: "series" },
                    itemStyle: { color: "#faad14" },
                    data: monitoringData,
                },
            ],
        };
    };

    // Monthly Trend Line Chart Option
    const getTrendOption = () => {
        const periods = edaTrend.map((item) => item.period);
        const totals = edaTrend.map((item) => item.total);
        const decided = edaTrend.map((item) => item.decided_plan);
        const monitoring = edaTrend.map((item) => item.monitoring);

        return {
            tooltip: {
                trigger: "axis",
            },
            legend: {
                data: ["Total Forces", "Decided Plan", "Monitoring"],
                bottom: 0,
            },
            grid: {
                top: "12%",
                left: "3%",
                right: "4%",
                bottom: "16%",
                containLabel: true,
            },
            xAxis: {
                type: "category",
                data: periods,
                axisLabel: {
                    fontSize: 11,
                    color: "#595959",
                },
            },
            yAxis: {
                type: "value",
                minInterval: 1,
                splitLine: {
                    lineStyle: { type: "dashed", color: "#f0f0f0" },
                },
            },
            series: [
                {
                    name: "Total Forces",
                    type: "line",
                    smooth: true,
                    itemStyle: { color: "#1677ff" },
                    lineStyle: { width: 3 },
                    data: totals,
                },
                {
                    name: "Decided Plan",
                    type: "line",
                    smooth: true,
                    itemStyle: { color: "#52c41a" },
                    data: decided,
                },
                {
                    name: "Monitoring",
                    type: "line",
                    smooth: true,
                    itemStyle: { color: "#fa8c16" },
                    data: monitoring,
                },
            ],
        };
    };

    return (
        <div style={{ marginTop: 18 }}>
            <Row gutter={[16, 16]}>
                {/* Chart 1: Dimension Frequency Distribution */}
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
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 6 }}>
                            <Space align="center">
                                <BarChartOutlined style={{ color: "#1677ff", fontSize: 16 }} />
                                <Text strong style={{ fontSize: 14 }}>Distribusi Frekuensi per Dimensi STEEP</Text>
                            </Space>
                            <Tag color="cyan" bordered={false}>Kepadatan Sektor</Tag>
                        </div>
                        <Text type="secondary" style={{ fontSize: 12 }}>
                            Konsentrasi driving forces aktif di setiap dimensi lingkungan makro dan mezzo.
                        </Text>

                        {!hasFrequencyData ? (
                            <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Belum ada data distribusi dimensi." />
                        ) : (
                            <div style={{ height: 260, marginTop: 10 }}>
                                <ReactEcharts
                                    option={getFrequencyOption()}
                                    style={{ height: "100%", width: "100%" }}
                                    notMerge={true}
                                    lazyUpdate={true}
                                />
                            </div>
                        )}
                    </Card>
                </Col>

                {/* Chart 2: Historical Monthly Trend */}
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
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 6 }}>
                            <Space align="center">
                                <LineChartOutlined style={{ color: "#52c41a", fontSize: 16 }} />
                                <Text strong style={{ fontSize: 14 }}>Tren Pertumbuhan Driving Forces Bulanan</Text>
                            </Space>
                            <Tag color="purple" bordered={false}>Dinamika Waktu</Tag>
                        </div>
                        <Text type="secondary" style={{ fontSize: 12 }}>
                            Evolusi jumlah driving force dan pergeseran status aksi sepanjang waktu.
                        </Text>

                        {!hasTrendData ? (
                            <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Belum ada data tren bulanan untuk periode ini." />
                        ) : (
                            <div style={{ height: 260, marginTop: 10 }}>
                                <ReactEcharts
                                    option={getTrendOption()}
                                    style={{ height: "100%", width: "100%" }}
                                    notMerge={true}
                                    lazyUpdate={true}
                                />
                            </div>
                        )}
                    </Card>
                </Col>
            </Row>
        </div>
    );
}
