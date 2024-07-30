import React, { useEffect } from "react";
import ReactEcharts from "echarts-for-react";
import { Card, Col, Row } from "antd";

export default function Radar() {
    const data = [
        { name: "Economic", value: 10, priority: "High Priority", symbol: "triangle", color: "#ff4d4f", coord: [0, 10] },
        { name: "Ecological", value: 12, priority: "High Priority", symbol: "triangle", color: "#ff4d4f", coord: [12, 0] },
        { name: "Regulation", value: 15, priority: "High Priority", symbol: "triangle", color: "#ff4d4f", coord: [0, -15] },
        { name: "Technology", value: 16, priority: "High Priority", symbol: "triangle", color: "#ff4d4f", coord: [-16, 0] },
        { name: "Substitute", value: 19, priority: "High Priority", symbol: "triangle", color: "#ff4d4f", coord: [19, 0] },
        { name: "Suppliers", value: 14, priority: "High Priority", symbol: "triangle", color: "#ff4d4f", coord: [0, -14] },
        { name: "Customers", value: 8, priority: "High Priority", symbol: "triangle", color: "#ff4d4f", coord: [-8, 0] },
        { name: "Competitors", value: 11, priority: "High Priority", symbol: "triangle", color: "#ff4d4f", coord: [0, 11] },
    ];

    const options = {
        title: {
            text: "Firms Foresight Radar",
            left: "center",
        },
        legend: {
            data: [
                "Economic",
                "Ecological",
                "Regulation",
                "Technology",
                "Substitute",
                "Suppliers",
                "Customers",
                "Competitors",
            ],
            bottom: 0,
        },
        radar: {
            indicator: [
                { name: "Economic", max: 20 },
                { name: "Ecological", max: 20 },
                { name: "Regulation", max: 20 },
                { name: "Technology", max: 20 },
                { name: "Substitute", max: 20 },
                { name: "Suppliers", max: 20 },
                { name: "Customers", max: 20 },
                { name: "Competitors", max: 20 },
            ],
            shape: "circle",
            splitNumber: 7,
            splitArea: {
                areaStyle: {
                    color: [
                        "rgb(166,166,166)",
                        "rgb(166,166,166)",
                        "rgb(166,166,166)",
                        "rgb(190,190,190)",
                        "rgb(190,190,190)",
                        "rgb(217,217,217)",
                        "rgb(217,217,217)",
                    ],
                },
            },
            axisLine: {
                lineStyle: {
                    color: "rgb(135, 134, 134)",
                },
            },
            splitLine: {
                lineStyle: {
                    color: [
                        "rgb(135, 134, 134)",
                        "rgb(135, 134, 134)",
                        "rgb(135, 134, 134)",
                        "rgb(135, 134, 134)",
                        "rgb(135, 134, 134)",
                        "rgb(135, 134, 134)",
                        "rgb(135, 134, 134)",
                    ],
                    width: 1,
                },
            },
            name: {
                textStyle: {
                    color: "#000",
                    backgroundColor: "#fff",
                    borderRadius: 3,
                    fontWeight: "bold",
                    padding: [3, 5],
                },
            },
        },
        series: [
            {
                name: "Priority",
                type: "radar",
                symbolSize: 10,
                lineStyle: {
                    color: "transparent",
                },
                areaStyle: {
                    opacity: 0,
                },
                data: [
                    {
                        value: [10, 12, 15, 16, 19, 14, 8, 11],
                        name: "Economic",
                        symbol: "triangle",
                    },
                    {
                        value: [8, 6, 9, 12, 15, 10, 5, 7],
                        name: "Ecological",
                        symbol: "triangle",
                    },
                    {
                        value: [5, 3, 4, 6, 8, 5, 2, 3],
                        name: "Regulation",
                        symbol: "triangle",
                    },
                    {
                        value: [12, 10, 13, 14, 16, 11, 7, 9],
                        name: "Technology",
                        symbol: "triangle",
                    },
                    {
                        value: [11, 9, 12, 13, 15, 10, 6, 8],
                        name: "Substitute",
                        symbol: "circle",
                    },
                    {
                        value: [7, 6, 8, 9, 12, 7, 5, 6],
                        name: "Suppliers",
                        symbol: "triangle",
                    },
                    {
                        value: [9, 8, 10, 11, 14, 9, 6, 8],
                        name: "Customers",
                        symbol: "triangle",
                    },
                    {
                        value: [13, 10, 14, 16, 17, 12, 10, 12],
                        name: "Competitors",
                        symbol: "circle",
                    },
                ],
            },
        ],
        markPoint: {
            data: [
                { name: "Economic", coord: [10, 0], symbol: "triangle", itemStyle: { color: "#ff4d4f" } },
                { name: "Ecological", coord: [0, 12], symbol: "triangle", itemStyle: { color: "#faad14" } },
                { name: "Regulation", coord: [-10, 0], symbol: "triangle", itemStyle: { color: "#52c41a" } },
                { name: "Technology", coord: [0, -12], symbol: "triangle", itemStyle: { color: "#1890ff" } },
                { name: "Substitute", coord: [6, 6], symbol: "circle", itemStyle: { color: "#13c2c2" } },
                { name: "Suppliers", coord: [-6, 6], symbol: "triangle", itemStyle: { color: "#13c2c2" } },
                { name: "Customers", coord: [-6, -6], symbol: "triangle", itemStyle: { color: "#13c2c2" } },
                { name: "Competitors", coord: [6, -6], symbol: "circle", itemStyle: { color: "#13c2c2" } },
            ],
        },
    };

    // const options = {
    //     title: {
    //         text: "Firms Radar Chart",
    //         left: "center",
    //     },
    //     legend: {
    //         data: [
    //             "Economic",
    //             "Ecological",
    //             "Regulation",
    //             "Technology",
    //             "Substitute",
    //             "Suppliers",
    //             "Customers",
    //             "Competitors",
    //         ],
    //         bottom: 0,
    //     },
    //     radar: {
    //         indicator: data.map(item => ({ name: item.name, max: 20 })),
    //         shape: "circle",
    //         splitNumber: 7, // Total number of splits (3 + 2 + 2)
    //         splitArea: {
    //             areaStyle: {
    //                 color: ["rgba(255,255,255,0)", "rgba(255,255,255,0)", "rgba(255,255,255,0)", "rgba(255,255,255,0)", "rgba(255,255,255,0)", "rgba(255,255,255,0)", "rgba(255,255,255,0)"],
    //             },
    //         },
    //         axisLine: {
    //             lineStyle: {
    //                 color: "gray",
    //             },
    //         },
    //         splitLine: {
    //             lineStyle: {
    //                 color: ["#d9d9d9", "#d9d9d9", "#d9d9d9", "#d9d9d9", "#d9d9d9", "#d9d9d9", "#d9d9d9"],
    //                 width: 1,
    //             },
    //         },
    //         name: {
    //             textStyle: {
    //                 color: "#000",
    //                 fontWeight: "bold",
    //                 backgroundColor: "#fff",
    //                 borderRadius: 3,
    //                 padding: [3, 5],
    //             },
    //         },
    //     },
    //     series: [
    //         {
    //             name: "Priority",
    //             type: "radar",
    //             symbolSize: 10,
    //             lineStyle: {
    //                 color: "transparent",
    //             },
    //             areaStyle: {
    //                 opacity: 0,
    //             },
    //             data: [
    //                 {
    //                     value: data.map(item => item.value),
    //                     name: "Priority",
    //                     symbol: "triangle",
    //                     itemStyle: {
    //                         color: "#ff4d4f",
    //                     },
    //                 },
    //             ],
    //             markPoint: {
    //                 symbolSize: 10,
    //                 data: data.map((item, index) => [
    //                     {
    //                         name: item.name,
    //                         coord: item.coord,
    //                         symbol: item.symbol,
    //                         itemStyle: {
    //                             color: item.color,
    //                         },
    //                     },
    //                     {
    //                         name: `${item.value}`,
    //                         coord: item.coord.map(coord => coord + 1),
    //                         symbol: 'none',
    //                         label: {
    //                             show: true,
    //                             formatter: `{c}`,
    //                             position: 'right',
    //                             color: item.color,
    //                             fontWeight: 'bold',
    //                         },
    //                     },
    //                 ]).flat(),
    //             },
    //         },
    //     ],
    // };

    return (
        <Card title="Technology Radar" style={{ margin: 20 }}>
            <Row gutter={16}>
                <Col span={24}>
                    <ReactEcharts
                        option={options}
                        style={{ height: "600px", width: "100%" }}
                    />
                </Col>
            </Row>
        </Card>
    );
}
