import {
    Button,
    Col,
    message,
    Row,
    Segmented,
    Skeleton,
    Table,
    Tooltip,
} from "antd";
import { useEffect, useState } from "react";
import dayjs from "dayjs";
import qs from "qs";

const OverallStatus = ({
    loading,
    setLoading,
    date,
    selectData,
}) => {
    const getParams = (params) => {
        return {
            results: params.pagination?.pageSize,
            page: params.pagination?.current,
            ...params,
        };
    };
    const [tableParams, setTableParams] = useState({
        pagination: {
            current: 1,
            pageSize: 20,
        },
        date: [
            dayjs().startOf("month").format("YYYY-MM-DD"),
            dayjs().endOf("month").format("YYYY-MM-DD"),
        ],
    });
    const [data, setData] = useState(null);
    const [segmented, setSegmented] = useState("Overall");

    useEffect(() => {
        fetchData();
    }, [
        tableParams.pagination?.pageSize,
        tableParams.pagination?.current,
        date,
        selectData,
    ]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const response = await axios.get(
                `${route("overall-status")}?${qs.stringify(
                    getParams({
                        ...tableParams,
                        date: date != null ? date.date : tableParams.date,
                        dimension: selectData ? selectData.dimension : null
                    })
                )}`
            );

            if (response.status == 200) {
                setTimeout(() => {
                    const newData = response.data.data.map((d, i) => ({
                        no: i + 1,
                        ...d,
                    }));

                    console.log(newData);

                    setData(newData);
                    setTableParams({
                        ...tableParams,
                        pagination: {
                            pageSize: response.data.meta.per_page,
                            current: response.data.meta.current_page,
                            total: response.data.meta.total,
                        },
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

    const renderToolTip = (record, type) => {
        return (
            <Tooltip placement="left" title={record.action_reason}>
                {type == "decided_plan"
                    ? record.decided_plan
                    : record.monitoring}
            </Tooltip>
        );
    };

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

                console.log(rowSpan, record.keyword);
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
                    render: (text, record) =>
                        renderToolTip(record, "decided_plan"),
                },
                {
                    title: "Monitor",
                    dataIndex: "monitoring",
                    align: "center",
                    render: (text, record) =>
                        renderToolTip(record, "monitoring"),
                },
            ],
        },
    ];

    const handleTableChange = (pagination, filters, sorter) => {
        setTableParams({
            pagination,
            date: tableParams.date,
            dimension: tableParams.dimension,
        });

        if (pagination.pageSize !== tableParams.pagination?.pageSize) {
            setData([]);
        }
    };

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
                        pagination={tableParams.pagination}
                        onChange={handleTableChange}
                    />
                </Col>
            </Row>
        </Skeleton>
    );
};

export default OverallStatus;
