import React, { useEffect } from "react";
import ReactEcharts from "echarts-for-react";
import { Card, Col, Row } from "antd";

export default function Radar() {
    const data = [
        {
            name: "Economic",
            value: 10,
            priority: "High Priority",
            symbol: "triangle",
            color: "#ff4d4f",
            coord: [0, 10],
        },
        {
            name: "Ecological",
            value: 12,
            priority: "High Priority",
            symbol: "triangle",
            color: "#ff4d4f",
            coord: [12, 0],
        },
        {
            name: "Regulation",
            value: 15,
            priority: "High Priority",
            symbol: "triangle",
            color: "#ff4d4f",
            coord: [0, -15],
        },
        {
            name: "Technology",
            value: 16,
            priority: "High Priority",
            symbol: "triangle",
            color: "#ff4d4f",
            coord: [-16, 0],
        },
        {
            name: "Substitute",
            value: 19,
            priority: "High Priority",
            symbol: "triangle",
            color: "#ff4d4f",
            coord: [19, 0],
        },
        {
            name: "Suppliers",
            value: 14,
            priority: "High Priority",
            symbol: "triangle",
            color: "#ff4d4f",
            coord: [0, -14],
        },
        {
            name: "Customers",
            value: 8,
            priority: "High Priority",
            symbol: "triangle",
            color: "#ff4d4f",
            coord: [-8, 0],
        },
        {
            name: "Competitors",
            value: 11,
            priority: "High Priority",
            symbol: "triangle",
            color: "#ff4d4f",
            coord: [0, 11],
        },
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
                { name: "Economic", max: 5 },
                { name: "Ecological", max: 5 },
                { name: "Regulation", max: 5 },
                { name: "Technology", max: 5 },
                { name: "Substitute", max: 5 },
                { name: "Suppliers", max: 5 },
                { name: "Customers", max: 5 },
                { name: "Competitors", max: 5 },
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
                        value: [1, 2, 3, 4, 1],
                        name: "Economic",
                        symbol: "triangle",
                    },
                    {
                        value: [1, 2, 3, 3, 5],
                        name: "Ecological",
                        symbol: "triangle",
                    },
                    {
                        value: [5, 3, 2, 3, 4],
                        name: "Regulation",
                        symbol: "triangle",
                    },
                    {
                        value: [2, 1, 3, 4, 1],
                        name: "Technology",
                        symbol: "triangle",
                    },
                    {
                        value: [5, 3, 2, 3, 4],
                        name: "Substitute",
                        symbol: "circle",
                    },
                    {
                        value: [5, 3, 2, 3, 4],
                        name: "Suppliers",
                        symbol: "triangle",
                    },
                    {
                        value: [5, 3, 2, 3, 4],
                        name: "Customers",
                        symbol: "triangle",
                    },
                    {
                        value: [5, 3, 2, 3, 4],
                        name: "Competitors",
                        symbol: "circle",
                    },
                ],
            },
        ],
        markPoint: {
            data: [
                {
                    name: "Economic",
                    coord: [10, 0],
                    symbol: "triangle",
                    itemStyle: { color: "#ff4d4f" },
                },
                {
                    name: "Ecological",
                    coord: [0, 12],
                    symbol: "triangle",
                    itemStyle: { color: "#faad14" },
                },
                {
                    name: "Regulation",
                    coord: [-10, 0],
                    symbol: "triangle",
                    itemStyle: { color: "#52c41a" },
                },
                {
                    name: "Technology",
                    coord: [0, -12],
                    symbol: "triangle",
                    itemStyle: { color: "#1890ff" },
                },
                {
                    name: "Substitute",
                    coord: [6, 6],
                    symbol: "circle",
                    itemStyle: { color: "#13c2c2" },
                },
                {
                    name: "Suppliers",
                    coord: [-6, 6],
                    symbol: "triangle",
                    itemStyle: { color: "#13c2c2" },
                },
                {
                    name: "Customers",
                    coord: [-6, -6],
                    symbol: "triangle",
                    itemStyle: { color: "#13c2c2" },
                },
                {
                    name: "Competitors",
                    coord: [6, -6],
                    symbol: "circle",
                    itemStyle: { color: "#13c2c2" },
                },
            ],
        },
    };

    return (
        <Row gutter={16} style={{ margin: 20 }}>
            <Col span={24}>
                <ReactEcharts
                    option={options}
                    style={{ height: "600px", width: "100%" }}
                />
            </Col>
        </Row>
    );
}
