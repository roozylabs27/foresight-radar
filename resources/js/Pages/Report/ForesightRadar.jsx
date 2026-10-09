import React, { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    Layout,
    DatePicker,
    Select,
    Button,
    Tag,
    Typography,
    Tooltip,
} from "antd";
import {
    FilterOutlined,
    CalendarOutlined,
    AppstoreOutlined,
    ReloadOutlined,
    RadarChartOutlined,
} from "@ant-design/icons";
import dayjs from "dayjs";
import Radar from "@/Components/Radar";

const { Content } = Layout;
const { Title } = Typography;
const { RangePicker } = DatePicker;

export default function ForesightRadar({ auth, dimensions, title }) {
    const [defaultDate, setDefaultDate] = useState([
        dayjs().startOf("month"),
        dayjs().endOf("month"),
    ]);
    const [loading, setLoading] = useState(false);
    const [newDate, setNewDate] = useState(null);
    const [selectData, setSelectData] = useState(null);

    const handleRangePickerChange = (dates) => {
        if (dates && dates.length === 2) {
            const formattedDates = dates.map((date) =>
                date.format("YYYY-MM-DD")
            );

            setDefaultDate(dates);
            setNewDate({ date: formattedDates });
        } else {
            const newStartDate = dayjs().startOf("month").format("YYYY-MM-DD");
            const newEndDate = dayjs().endOf("month").format("YYYY-MM-DD");

            setDefaultDate([dayjs(newStartDate), dayjs(newEndDate)]);
            setNewDate({ date: [newStartDate, newEndDate] });
        }
    };

    const handleSelectChange = (field, value) => {
        setSelectData({
            [field]: value !== undefined ? value : null,
        });
    };

    const handleResetFilter = () => {
        const start = dayjs().startOf("month");
        const end = dayjs().endOf("month");
        setDefaultDate([start, end]);
        setNewDate({ date: [start.format("YYYY-MM-DD"), end.format("YYYY-MM-DD")] });
        setSelectData(null);
    };

    const isFiltered = !!selectData?.dimension || !!newDate;

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <RadarChartOutlined style={{ color: "#1677ff", fontSize: 18 }} />
                    <Title level={4} style={{ margin: 0, color: "#1f1f1f" }}>
                        {title}
                    </Title>
                    <Tag
                        color="blue"
                        bordered={false}
                        style={{ fontSize: 11, fontWeight: 600, borderRadius: 10 }}
                    >
                        360° Strategic Horizon
                    </Tag>
                </div>
            }
        >
            <Head title={title} />

            <Content
                style={{
                    padding: "12px 18px 8px",
                    display: "flex",
                    flexDirection: "column",
                    minHeight: "calc(100vh - 104px)",
                    boxSizing: "border-box",
                }}
            >
                {/* Compact Single-Row Filter Toolbar */}
                <div className="radar-filter-bar">
                    <div
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: 14,
                            flexWrap: "wrap",
                        }}
                    >
                        <div
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: 6,
                                color: "#1677ff",
                                fontWeight: 600,
                                fontSize: 13,
                            }}
                        >
                            <FilterOutlined />
                            <span>Filter Analisis:</span>
                        </div>

                        {/* Periode Tanggal */}
                        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <span
                                style={{
                                    fontSize: 12,
                                    color: "#6b7280",
                                    fontWeight: 500,
                                }}
                            >
                                <CalendarOutlined style={{ marginRight: 4 }} />
                                Periode:
                            </span>
                            <RangePicker
                                value={defaultDate}
                                disabled={loading}
                                onChange={handleRangePickerChange}
                                format="YYYY-MM-DD"
                                style={{ width: 230 }}
                                size="middle"
                            />
                        </div>

                        {/* Dimensi STEEP */}
                        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <span
                                style={{
                                    fontSize: 12,
                                    color: "#6b7280",
                                    fontWeight: 500,
                                }}
                            >
                                <AppstoreOutlined style={{ marginRight: 4 }} />
                                Dimensi:
                            </span>
                            <Select
                                style={{ width: 210 }}
                                disabled={loading}
                                placeholder="Semua Dimensi"
                                filterOption={(input, option) =>
                                    (option?.label ?? "")
                                        .toLowerCase()
                                        .includes(input.toLowerCase())
                                }
                                allowClear
                                value={selectData?.dimension}
                                onChange={(value) =>
                                    handleSelectChange("dimension", value)
                                }
                                options={dimensions}
                                size="middle"
                            />
                        </div>

                        {/* Tombol Reset Filter jika aktif */}
                        {isFiltered && (
                            <Tooltip title="Reset ke default bulan ini">
                                <Button
                                    size="small"
                                    type="text"
                                    icon={<ReloadOutlined />}
                                    onClick={handleResetFilter}
                                    style={{ color: "#8c8c8c", fontSize: 12 }}
                                >
                                    Reset
                                </Button>
                            </Tooltip>
                        )}
                    </div>

                    <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                        <Tag
                            color="cyan"
                            bordered={false}
                            style={{ margin: 0, padding: "2px 8px", fontSize: 11 }}
                        >
                            Horizon 1 / 2 / 3
                        </Tag>
                    </div>
                </div>

                {/* Expansive Radar Visualization & Signals Container */}
                <div style={{ flex: 1, width: "100%" }}>
                    <Radar
                        loading={loading}
                        setLoading={setLoading}
                        date={newDate}
                        selectData={selectData}
                    />
                </div>
            </Content>
        </AuthenticatedLayout>
    );
}
