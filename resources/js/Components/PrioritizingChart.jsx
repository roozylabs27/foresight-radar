import React, { useEffect, useState } from "react";
import ReactEcharts from "echarts-for-react";
import qs from "qs";
import { Row, Col, Table, message } from "antd";

const PrioritizingChart = () => {
    const getParams = (params) => {
        return {
            ...params,
        };
    };
    const [positions, setPositions] = useState([]);
    const [tableParams, setTableParams] = useState({
        // date: [
        //     dayjs().startOf("month").format("YYYY-MM-DD"),
        //     dayjs().endOf("month").format("YYYY-MM-DD"),
        // ],
    });
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [option, setOption] = useState({
        title: {
            text: "Matrix of Uncertainty and Impact Analysis",
            left: "center",
            top: 10,
        },
        tooltip: {
            show: false,
        },
        xAxis: {
            type: "category",
            name: "IMPACT ANALYSIS",
            data: Array.from({ length: 10 }, (_, i) => i + 1),
            nameLocation: "middle",
            nameGap: 25,
            splitLine: {
                show: true,
            },
        },
        yAxis: {
            type: "category",
            name: "UNCERTAINTY ANALYSIS",
            data: Array.from({ length: 10 }, (_, i) => i + 1),
            nameLocation: "middle",
            nameGap: 25,
            splitLine: {
                show: true,
            },
        },
        grid: {
            top: 60,
            bottom: 50,
            left: 50,
            right: 10,
        },
        series: [
            {
                name: "Prioritizing",
                type: "scatter",
                data: positions,
                // [
                //     [0, 0, 1],
                //     [2, 2, 2],
                //     [8, 8, 3],
                //     [8, 6, 4],
                //     [7, 8, 5],
                //     [7, 10, 6],
                //     [6, 8, 7],
                //     [6, 7, 8],
                //     [5, 8, 9],
                //     [5, 6, 10],
                //     [5, 5, 11],
                //     [4, 4, 12],
                //     [4, 3, 13],
                //     [3, 10, 14],
                //     [3, 8, 15],
                //     [3, 6, 16],
                //     [2, 8, 17],
                //     [2, 6, 18],
                //     [6, 6, 19],
                //     [7, 6, 20],
                // ],
                symbolSize: 20,
                label: {
                    show: true,
                    formatter: "{@[2]}",
                    color: "#fff",
                },
                itemStyle: {
                    color: function (params) {
                        const x = params.value[0];
                        const y = params.value[1];
                        if (x >= 5 && y >= 5) {
                            return "#ff4d4f"; // High priority (red)
                        } else if ((x >= 5 && y <= 4) || (y >= 5 && x <= 4)) {
                            return "#faad14"; // Medium priority (yellow)
                        } else {
                            return "#52c41a"; // Low priority (green)
                        }
                    },
                },
            },
        ],
    });

    useEffect(() => {
        fetchData();
    }, [tableParams?.dimension, option, positions]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const response = await axios.get(
                `${route("prioritizing")}?${qs.stringify(
                    getParams(tableParams)
                )}`
            );

            if (response.status == 200) {
                setTimeout(() => {
                    const newData = response.data.map((d, i) => {
                        const newPosition = d.position.push(i + 1);

                        positions.push(d.position);
                        return {
                            no: i + 1,
                            position: newPosition,
                            ...d,
                        };
                    });
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
            key: "no",
            width: 10,
            align: "center",
        },
        {
            title: "Keyword",
            dataIndex: "keyword",
            key: "keyword",
            width: 250,
        },
    ];

    return (
        <Row gutter={16} style={{ margin: 20 }}>
            <Col span={8}>
                <Table
                    columns={columns}
                    dataSource={data}
                    bordered
                    loading={loading}
                    rowKey={(record) => record.no}
                    // onChange={handleTableChange}
                    title={() => (
                        <div
                            style={{ textAlign: "center", fontWeight: "bold" }}
                        >
                            {tableParams?.dimension
                                ? `${showActiveDimension(
                                      tableParams.dimension
                                  )}`
                                : "OVERALL"}
                        </div>
                    )}
                />
            </Col>
            <Col span={16}>
                <ReactEcharts
                    option={option}
                    style={{ height: "600px", width: "100%" }}
                />
            </Col>
        </Row>
    );
};

export default PrioritizingChart;
