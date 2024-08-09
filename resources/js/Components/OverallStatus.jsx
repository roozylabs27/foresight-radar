import { CheckOutlined } from "@ant-design/icons";
import { Col, message, Row, Segmented, Skeleton, Table } from "antd";
import { useEffect, useState } from "react";
import dayjs from "dayjs";
import qs from "qs";

const OverallStatus = ({ loading, setLoading, date }) => {
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
    const [data, setData] = useState(null);
    const [segmented, setSegmented] = useState("Overall");

    useEffect(() => {
        fetchData();
    }, [date]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const response = await axios.get(
                `${route("overall-status")}?${qs.stringify(
                    getParams({
                        ...tableParams,
                        date: date != null ? date.date : tableParams.date,
                    })
                )}`
            );

            if (response.status == 200) {
                setTimeout(() => {
                    const newData = response.data.map((d, i) => ({
                        no: i + 1,
                        ...d,
                    }));

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

    const columnsDetail = [
        {
            title: "DIMENSION",
            dataIndex: "dimension",
            onCell: (record, rowIndex) => {
                const rowSpan = data.reduce((acc, cur, curIndex) => {
                    if (
                        cur.dimension === record.dimension &&
                        curIndex >= rowIndex
                    ) {
                        return acc + 1;
                    }
                    return acc;
                }, 0);

                if (
                    rowIndex === 0 ||
                    data[rowIndex - 1].dimension !== record.dimension
                ) {
                    return { rowSpan };
                }

                return { rowSpan: 0 };
            },
        },
        {
            title: "",
            dataIndex: "no",
            align: "center",
        },
        {
            title: "Driving Force Detail",
            dataIndex: "description",
        },
    ];

    const columns = [
        {
            title: "DIMENSION",
            dataIndex: "dimension",
            onCell: (record, rowIndex) => {
                const rowSpan = data.reduce((acc, cur, curIndex) => {
                    if (
                        cur.dimension === record.dimension &&
                        curIndex >= rowIndex
                    ) {
                        return acc + 1;
                    }
                    return acc;
                }, 0);

                if (
                    rowIndex === 0 ||
                    data[rowIndex - 1].dimension !== record.dimension
                ) {
                    return { rowSpan };
                }

                return { rowSpan: 0 };
            },
        },
        {
            title: "",
            dataIndex: "no",
            align: "center",
        },
        {
            title: "DRIVING FORCE",
            dataIndex: "keyword",
        },
        {
            title: "TIME HORIZON",
            children: [
                {
                    title: "Short Term",
                    dataIndex: "short_term",
                    align: "center",
                },
                {
                    title: "Mid Term",
                    dataIndex: "mid_term",
                    align: "center",
                },
                {
                    title: "Long Term",
                    dataIndex: "long_term",
                    align: "center",
                },
            ],
        },
        {
            title: "PRIORITIZING",
            children: [
                {
                    title: "High",
                    align: "center",
                    dataIndex: "high",
                },
                {
                    title: "Medium",
                    align: "center",
                    dataIndex: "medium",
                },
                {
                    title: "Low",
                    align: "center",
                    dataIndex: "low",
                },
            ],
        },
        {
            title: "STATUS OF ACTION",
            children: [
                {
                    title: "Decided Plan",
                    dataIndex: "decided_plan",
                    align: "center",
                },
                {
                    title: "Monitor",
                    dataIndex: "monitoring",
                    align: "center",
                },
            ],
        },
    ];

    return (
        <Skeleton active loading={false}>
            <Row gutter={24}>
                <Col span={24}>
                    <Segmented
                        options={["Overall", "Detail Info"]}
                        onChange={(value) => {
                            setSegmented(value);
                            setLoading(true);

                            setTimeout(() => {
                                setLoading(false);
                            }, 500);
                        }}
                    />
                </Col>
                <Col span={24}>
                    <Table
                        columns={
                            segmented == "Overall" ? columns : columnsDetail
                        }
                        dataSource={data}
                        bordered
                        loading={loading}
                        rowKey={(record) => record.id}
                        pagination={false}
                    />
                </Col>
            </Row>
        </Skeleton>
    );
};

export default OverallStatus;
