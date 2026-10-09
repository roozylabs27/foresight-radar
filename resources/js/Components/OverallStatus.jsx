import {
    Button,
    Col,
    message,
    notification,
    Row,
    Segmented,
    Skeleton,
    Table,
    Tooltip,
} from "antd";
import { FileExcelOutlined } from "@ant-design/icons";
import { useEffect, useState } from "react";
import dayjs from "dayjs";
import qs from "qs";
import * as XLSX from "xlsx";

const OverallStatus = ({ loading, setLoading, date, selectedData, permissions = [] }) => {
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
        selectedData?.dimension,
        selectedData?.time_horizon,
        selectedData?.priority,
        selectedData?.status_action,
    ]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const response = await axios.get(
                `${route(
                    "visualization.registered-list.get-data"
                )}?${qs.stringify(
                    getParams({
                        ...tableParams,
                        date: date != null ? date.date : tableParams.date,
                        ...(selectedData || {}),
                    })
                )}`
            );

            if (response.status == 200) {
                setTimeout(() => {
                    const newData = response.data.data.map((d, i) => ({
                        no: i + 1,
                        ...d,
                    }));

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

    const handleExport = async () => {
        setLoading(true);
        try {
            const response = await axios.get(
                `${route(
                    "visualization.registered-list.export-data"
                )}?${qs.stringify(
                    getParams({
                        ...tableParams,
                        date: date != null ? date.date : tableParams.date,
                        ...(selectedData || {}),
                    })
                )}`
            );

            const exportData = response.data.map((record, index) => ({
                No: index + 1,
                Dimension: record.dimension,
                Keyword: record.keyword,
                Description: record.description,
                "Short Term": record.short_term,
                "Mid Term": record.mid_term,
                "Long Term": record.long_term,
                High: record.high,
                Medium: record.medium,
                Low: record.low,
                "Decided Plan": record.decided_plan,
                Monitor: record.monitoring,
            }));

            const worksheet = XLSX.utils.json_to_sheet(exportData);

            const merges = [];
            let startRow = 2; // Excel rows are 1-indexed
            let prevDimension = exportData[0].Dimension;
            let rowCount = 1;

            for (let i = 1; i < exportData.length; i++) {
                if (exportData[i].Dimension === prevDimension) {
                    rowCount++;
                } else {
                    if (rowCount > 1) {
                        merges.push({
                            s: { r: startRow - 1, c: 1 }, // starting cell (row, col)
                            e: { r: startRow + rowCount - 2, c: 1 }, // ending cell (row, col)
                        });

                        // Atur centering untuk sel yang digabungkan
                        for (
                            let j = startRow - 1;
                            j <= startRow + rowCount - 2;
                            j++
                        ) {
                            const cellRef = XLSX.utils.encode_cell({
                                r: j,
                                c: 1,
                            });
                            if (!worksheet[cellRef]) worksheet[cellRef] = {};
                            worksheet[cellRef].s = {
                                alignment: {
                                    vertical: "center",
                                    horizontal: "center",
                                },
                            };
                        }
                    }
                    startRow += rowCount;
                    rowCount = 1;
                    prevDimension = exportData[i].Dimension;
                }
            }

            // Handle merge for the last set of rows
            if (rowCount > 1) {
                merges.push({
                    s: { r: startRow - 1, c: 1 }, // starting cell (row, col)
                    e: { r: startRow + rowCount - 2, c: 1 }, // ending cell (row, col)
                });

                for (let j = startRow - 1; j <= startRow + rowCount - 2; j++) {
                    const cellRef = XLSX.utils.encode_cell({ r: j, c: 1 });
                    if (!worksheet[cellRef]) worksheet[cellRef] = {};
                    worksheet[cellRef].s = {
                        alignment: {
                            vertical: "center",
                            horizontal: "center",
                        },
                    };
                }
            }

            worksheet["!merges"] = merges;

            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(
                workbook,
                worksheet,
                "Registered List"
            );

            XLSX.writeFile(
                workbook,
                `${dayjs().format("YYYY-MM-DD")} - Registered List Data.xlsx`
            );

            setTimeout(() => {
                setLoading(false);
            }, 1000);
        } catch (error) {
            setLoading(false);
            notification["warning"]("Error", error);
        }
    };

    return (
        <Skeleton active loading={false}>
            <div style={{ display: "flex", flexDirection: "column", height: "100%" }}>
                <Row justify="space-between" align="middle" style={{ marginBottom: 12 }}>
                    <Col>
                        <Segmented
                            options={["Overall", "Detail Info"]}
                            value={segmented}
                            onChange={(value) => {
                                setSegmented(value);
                                setLoading(true);

                                setTimeout(() => {
                                    setLoading(false);
                                }, 300);
                            }}
                        />
                    </Col>
                    {permissions?.includes("export-registered-list") && (
                        <Col>
                            <Button
                                onClick={handleExport}
                                disabled={loading}
                                loading={loading}
                                type="primary"
                                icon={<FileExcelOutlined />}
                            >
                                Export to Excel
                            </Button>
                        </Col>
                    )}
                </Row>
                <Table
                    columns={
                        segmented == "Overall" ? columns : columnsDetail
                    }
                    dataSource={data}
                    bordered
                    size="middle"
                    loading={loading}
                    rowKey={(record) => record.id}
                    pagination={tableParams.pagination}
                    onChange={handleTableChange}
                    scroll={{ x: "max-content", y: 460 }}
                />
            </div>
        </Skeleton>
    );
};

export default OverallStatus;
