import React, { useEffect, useState } from "react";
import ReactEcharts from "echarts-for-react";
import qs from "qs";
import { Row, Col, Table, message, Skeleton } from "antd";
import dayjs from "dayjs";

const PrioritizingChart = ({
    loading,
    setLoading,
    date,
    selectData,
    dimensions,
}) => {
    const getParams = (params) => {
        return {
            ...params,
        };
    };
    const [positions, setPositions] = useState([]);
    const [tableParams, setTableParams] = useState({
        date: [
            dayjs().startOf("month").format("YYYY-MM-DD"),
            dayjs().endOf("month").format("YYYY-MM-DD"),
        ],
    });
    const [data, setData] = useState(null);
    const [option, setOption] = useState({
        title: {
            text: "Matrix of Uncertainty and Impact Analysis",
            left: "center",
            top: 10,
        },
        legend: {
            data: ["High Priority", "Medium Priority", "Low Priority"],
            left: 10,
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
                symbolSize: 40,
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
    }, [date, selectData]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const response = await axios.get(
                `${route("visualization.prioritizing.get-data")}?${qs.stringify(
                    getParams({
                        ...tableParams,
                        date : date != null ? date.date : tableParams.date,
                        dimension: selectData ? selectData.dimension : null
                    })
                )}`
            );

            if (response.status == 200) {
                setTimeout(() => {
                    const newPositions = response.data.map((d, i) => {
                        d.position.push(i + 1);
                        return d.position;
                    });
                    const newData = response.data.map((d, i) => ({
                        no: i + 1,
                        ...d,
                    }));

                    const adjustedPositions =
                        adjustOverlappingPositions(newPositions);

                    setPositions(adjustedPositions);
                    setData(newData);
                    setTableParams({
                        ...tableParams,
                    });
                    setOption((prevOption) => ({
                        ...prevOption,
                        series: [
                            {
                                ...prevOption.series[0],
                                data: adjustedPositions,
                            },
                        ],
                    }));

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

    const adjustOverlappingPositions = (positions) => {
        const adjustedPositions = [];
        const seen = new Map();

        positions.forEach((pos) => {
            let [x, y, id] = pos;
            const key = `${x}-${y}`;
            if (seen.has(key)) {
                seen.get(key).push(id);
            } else {
                seen.set(key, [id]);
                adjustedPositions.push([x, y, id]);
            }
        });

        // Convert seen map to adjusted positions
        return adjustedPositions.map((pos) => {
            const key = `${pos[0]}-${pos[1]}`;
            const ids = seen.get(key);
            return [pos[0], pos[1], ids];
        });
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

    const showActiveDimension = (dimension) => {
        if (dimension != null) {
            const { label } = dimensions.find((d) => d.value === dimension);

            return label.toUpperCase();
        }

        return "OVERALL";
    };
    return (
        <Row gutter={16} style={{ margin: 0, alignItems: "stretch" }}>
            <Col xs={24} lg={8}>
                <Table
                    columns={columns}
                    dataSource={data}
                    bordered
                    size="middle"
                    loading={loading}
                    rowKey={(record) => record.no}
                    pagination={false}
                    scroll={{ y: 440 }}
                    title={() => (
                        <div
                            style={{ textAlign: "center", fontWeight: 600, color: "#1f1f1f" }}
                        >
                            {selectData
                                ? `${showActiveDimension(selectData.dimension)}`
                                : "OVERALL"}
                        </div>
                    )}
                />
            </Col>
            <Col xs={24} lg={16}>
                <Skeleton active loading={loading}>
                    <ReactEcharts
                        option={option}
                        style={{ height: "480px", width: "100%" }}
                    />
                </Skeleton>
            </Col>
        </Row>
    );
};

export default PrioritizingChart;
