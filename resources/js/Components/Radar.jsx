import React, { useEffect, useState } from "react";
import ReactEcharts from "echarts-for-react";
import { Col, message, Row, Skeleton, Table } from "antd";
import dayjs from "dayjs";
import qs from "qs";
import "../../css/additional.css";

export default function Radar({ loading, setLoading, date, selectData }) {
    const getParams = (params) => {
        return {
            ...params,
        };
    };
    const [tableParams, setTableParams] = useState({
        date: [
            dayjs().startOf("month").format("YYYY-MM-DD"),
            dayjs().endOf("month").format("YYYY-MM-DD"),
        ],
    });
    const [radarData, setRadarData] = useState([]);
    const [data, setData] = useState(null);
    const options = {
        title: {
            text: "Firms Foresight Radar",
            left: "center",
        },
        legend: {
            data: [
                "Economy",
                "Ecology",
                "Regulation",
                "Technology",
                "Substitute",
                "Supplier",
                "Customer",
                "Competitor",
            ],
            bottom: 0,
        },
        radar: {
            indicator: [
                { name: "Economy", max: 5 },
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
                symbolSize: 15,
                label: {
                    show: function (params) {
                        console.log(params);
                        return true;
                    },
                    formatter: "{@[2]}",
                    fontWeight: "bold",
                    backgroundColor: "#000",
                    padding: [3, 3],
                    borderRadius: 50,
                    color: "#fff",
                },
                lineStyle: {
                    color: "transparent",
                },
                areaStyle: {
                    opacity: 0,
                },
                data: radarData,
                // [
                //     {
                //         value: [1, 2, 3, 3, 5],
                //         name: "Economic",
                //         symbol: "triangle",
                //         itemStyle: {
                //             color: "#ff4d4f",
                //         },
                //     },
                //     {
                //         value: [1, 2, 3, 3, 5],
                //         name: "Ecological",
                //         symbol: "triangle",
                //         itemStyle: {
                //             color: "#ff4d4f",
                //         },
                //     },
                //     {
                //         value: [5, 3, 2, 3, 4],
                //         name: "Regulation",
                //         symbol: "triangle",
                //         itemStyle: {
                //             color: "#ff4d4f",
                //         },
                //     },
                //     {
                //         value: [2, 1, 3, 4, 1],
                //         name: "Technology",
                //         symbol: "triangle",
                //         itemStyle: {
                //             color: "#ff4d4f",
                //         },
                //     },
                //     {
                //         value: [5, 3, 2, 3, 4],
                //         name: "Substitute",
                //         symbol: "circle",
                //         itemStyle: {
                //             color: "#ff4d4f",
                //         },
                //     },
                //     {
                //         value: [5, 3, 2, 3, 4],
                //         name: "Suppliers",
                //         symbol: "triangle",
                //         itemStyle: {
                //             color: "#ff4d4f",
                //         },
                //     },
                //     {
                //         value: [5, 3, 2, 3, 4],
                //         name: "Customers",
                //         symbol: "triangle",
                //         itemStyle: {
                //             color: "#ff4d4f",
                //         },
                //     },
                //     {
                //         value: [5, 3, 2, 3, 4],
                //         name: "Competitors",
                //         symbol: "circle",
                //         itemStyle: {
                //             color: "#ff4d4f",
                //         },
                //     },
                // ],
            },
        ],
    };

    useEffect(() => {
        fetchData();
    }, [date, selectData]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const response = await axios.get(
                `${route("foresight-radar")}?${qs.stringify(
                    getParams({
                        ...tableParams,
                        date: date != null ? date.date : tableParams.date,
                        dimension: selectData ? selectData.dimension : null,
                    })
                )}`
            );

            if (response.status == 200) {
                setTimeout(() => {
                    const newData = response.data.map((d, i) => ({
                        no: i + 1,
                        ...d,
                    }));

                    const radar = response.data.map((d) => ({
                        value: d.value,
                        name: d.dimension,
                        symbol: d.symbol,
                        itemStyle: {
                            color: d.item_style,
                        },
                    }));
                    setRadarData(radar);

                    console.log(radarData);
                    setData(newData);
                    setTableParams({
                        ...tableParams,
                    });

                    setLoading(false);
                }, 500);
            } else {
                message.error(`Error: ${error.message}`);
                setLoading(false);
            }
        } catch (error) {
            // TODO: Handling error
            message.error(`Error: ${error.message}`);
            setLoading(false);
        }
    };

    const columns = [
        {
            title: "No",
            dataIndex: "no",
        },
        {
            title: "Signal of Changes",
            dataIndex: "keyword",
        },
        {
            title: "Act",
            dataIndex: "status_action",
            align: "center",
        },
        {
            title: "ST",
            dataIndex: "short_term",
            align: "center",
        },
        {
            title: "MT",
            dataIndex: "mid_term",
            align: "center",
        },
        {
            title: "LT",
            dataIndex: "long_term",
            align: "center",
        },
    ];

    const getRowClassName = (record) => {
        if (record.priority.toLowerCase() === "high")
            return "red-background disable-hover";
        if (record.priority.toLowerCase() === "medium")
            return "yellow-background disable-hover";
        if (record.priority.toLowerCase() === "low")
            return "green-background disable-hover";
        return "disable-hover";
    };

    setTimeout(() => {
        setLoading(false);
    }, 1000);

    return (
        <Row gutter={16} style={{ margin: 20 }}>
            <Col span={8}>
                <Table
                    columns={columns}
                    dataSource={data}
                    bordered
                    loading={loading}
                    rowClassName={getRowClassName}
                    rowKey={(record) => record.no}
                    pagination={false}
                />
            </Col>
            <Col span={16}>
                <Skeleton loading={loading} active>
                    <ReactEcharts
                        notMerge={true}
                        option={options}
                        style={{ height: "600px", width: "100%" }}
                    />
                </Skeleton>
            </Col>
        </Row>
    );
}
